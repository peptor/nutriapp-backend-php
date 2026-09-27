<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Support\AlertTexts;
use App\Support\UrlHelper;
use Illuminate\Http\Request;

// Alertes (reg_alerts): llista, recompte per a la insígnia del menú i canvi d'estat.
// El nutricionista veu les dels seus pacients i en gestiona l'estat; el pacient veu les de les seves rutines i només en marca el seu "vist".
// Vegeu docs/com-funcionen-les-alertes.md.
class AlertsController extends Controller
{
    private function own(Request $request)
    {
        $user = $request->user();

        return Alert::whereHas('assignment.patient', fn ($q) => $user->role === 'PACIENT'
            ? $q->where('userId', $user->id)
            : $q->where('nutricionistaId', $user->id));
    }

    // Nutricionista: ?status=pending (noves, OPEN; per defecte) | seen (SEEN) | resolved | all (tot l'històric).
    // Pacient: ?status=pending (encara no vistes per ell) | seen (ja vistes) | all, independent de l'estat del nutricionista.
    // ?assignmentId=… restringeix a una sola rutina (secció "Notes"/"Incidències" de l'Evolució, amb `all`: tot
    // l'històric, resoltes incloses — no és una safata de feina, és el registre de com ha anat la rutina).
    // Urgents primer, i després les més recents.
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $role = $request->user()->role;
        $language = $request->user()->language;
        $query = $this->baseQuery($request);
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }
        if ($status === 'all') {
            // Sense filtre d'estat: tot l'històric.
        } elseif ($role === 'PACIENT') {
            $status === 'seen' ? $query->whereNotNull('patientSeenAt') : $query->whereNull('patientSeenAt');
        } elseif ($status === 'pending') {
            $query->where('status', 'OPEN');
        } elseif ($status === 'seen') {
            $query->where('status', 'SEEN');
        } elseif ($status === 'resolved') {
            $query->where('status', 'RESOLVED');
        }

        $items = $query
            ->orderByRaw("CASE level WHEN 'URGENT' THEN 0 ELSE 1 END")
            ->orderBy('recordDate', 'desc')
            ->orderBy('createdAt', 'desc')
            ->limit(300)
            ->get()
            ->map(fn (Alert $alert) => $this->formatAlert($alert, $role, $language));

        return response()->json($items);
    }

    // Avui del pacient: alertes fora de rang o urgents (qualsevol data) + qualsevol altre avís d'avui o ahir,
    // independent de l'estat (es marca amb la insígnia si ja està vista/resolta). Vegeu docs/com-funcionen-les-alertes.md.
    public function today(Request $request)
    {
        $role = $request->user()->role;
        $language = $request->user()->language;
        $yesterday = now()->subDay()->toDateString();

        $items = $this->baseQuery($request)
            ->where(function ($q) use ($yesterday) {
                $q->where('type', 'OUT_OF_RANGE')->orWhere('level', 'URGENT')->orWhereDate('recordDate', '>=', $yesterday);
            })
            ->orderByRaw("CASE level WHEN 'URGENT' THEN 0 ELSE 1 END")
            ->orderBy('recordDate', 'desc')
            ->limit(50)
            ->get()
            ->map(fn (Alert $alert) => $this->formatAlert($alert, $role, $language));

        return response()->json($items);
    }

    private function baseQuery(Request $request)
    {
        return $this->own($request)->with(['assignment:id,patientId,templateId', 'assignment.patient:id,userId,photoUrl', 'assignment.patient.user:id,name', 'assignment.patient.nutricionista:id,name', 'assignment.template:id,name', 'assignment.template.fields']);
    }

    private function formatAlert(Alert $alert, string $role, ?string $language): array
    {
        return [
            'id' => $alert->id,
            'assignmentId' => $alert->assignmentId,
            'patientId' => $alert->assignment->patientId,
            'nutricionistaId' => $alert->assignment->patient->nutricionistaId,
            'nutricionistaName' => $alert->assignment->patient->nutricionista?->name,
            'patientName' => $alert->assignment->patient->user->name,
            'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($alert->assignment->patient->photoUrl),
            'routineName' => $alert->assignment->template->name,
            'fieldName' => $alert->fieldName,
            'type' => $alert->type,
            'recordDate' => $alert->recordDate->toDateString(),
            'level' => $alert->level,
            'severity' => $alert->severity,
            'reference' => $alert->reference,
            'message' => $alert->message,
            // Al final: 'title'/'body'/'advice'/'label'/'value' (aquest últim ja formatat, p. ex. "8/10"), pot
            // sobreescriure qualsevol clau anterior amb el mateix nom si mai coincidissin.
            ...AlertTexts::for($alert, $alert->assignment->template->fields->firstWhere('name', $alert->fieldName), $role, $language),
            'status' => $alert->status,
            'seenAt' => $alert->seenAt,
            'resolvedAt' => $alert->resolvedAt,
            'patientSeenAt' => $alert->patientSeenAt,
        ];
    }

    // Comptadors per a la insígnia del menú: urgents oberts (sense veure) i totals pendents.
    public function summary(Request $request)
    {
        $rows = $this->own($request)->whereIn('status', ['OPEN', 'SEEN'])->selectRaw('level, status, count(*) as n')->groupBy('level', 'status')->get();
        $count = fn (string $level, array $statuses) => (int) $rows->where('level', $level)->whereIn('status', $statuses)->sum('n');

        return response()->json([
            'urgentOpen' => $count('URGENT', ['OPEN']),
            'reviewOpen' => $count('REVIEW', ['OPEN']),
            'urgentPending' => $count('URGENT', ['OPEN', 'SEEN']),
            'reviewPending' => $count('REVIEW', ['OPEN', 'SEEN']),
            // Numeret del menú Avisos del pacient: avisos que encara no ha marcat com a vistos.
            'patientNew' => $request->user()->role === 'PACIENT' ? $this->own($request)->whereNull('patientSeenAt')->count() : 0,
        ]);
    }

    // El pacient marca un avís com a vist (seen = true) o el torna a pendent (seen = false). No toca l'estat del nutricionista.
    public function patientSeen(Request $request, string $id)
    {
        $data = $request->validate(['seen' => ['required', 'boolean']]);
        $alert = $this->own($request)->find($id);
        if (! $alert) {
            return response()->json(['error' => 'Alerta no trobada'], 404);
        }

        $alert->patientSeenAt = $data['seen'] ? ($alert->patientSeenAt ?? now()) : null;
        $alert->save();

        return response()->json(['id' => $alert->id, 'patientSeenAt' => $alert->patientSeenAt]);
    }

    // Canvia l'estat: OPEN (tornar a obrir), SEEN (vista) o RESOLVED (resolta).
    public function update(Request $request, string $id)
    {
        $data = $request->validate(['status' => ['required', 'in:OPEN,SEEN,RESOLVED']]);
        $alert = $this->own($request)->find($id);
        if (! $alert) {
            return response()->json(['error' => 'Alerta no trobada'], 404);
        }

        $alert->status = $data['status'];
        $alert->seenAt = $data['status'] === 'OPEN' ? null : ($alert->seenAt ?? now());
        $alert->resolvedAt = $data['status'] === 'RESOLVED' ? now() : null;
        $alert->save();

        return response()->json(['id' => $alert->id, 'status' => $alert->status, 'seenAt' => $alert->seenAt, 'resolvedAt' => $alert->resolvedAt]);
    }
}

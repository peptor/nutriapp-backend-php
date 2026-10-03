<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Support\NoticeFeed;
use App\Support\Paged;
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
    // Urgents primer (llevat de ?order=date), i després les més recents. PAGINADA: ?page&perPage → { data, page, perPage, total, hasMore }.
    public function index(Request $request)
    {
        $role = $request->user()->role;
        $language = $request->user()->language;
        $query = $this->baseQuery($request);
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }
        NoticeFeed::applyAlertStatus($query, $role, $request->query('status', 'pending'));
        // ?order=date: només per data (la targeta «Notes»); per defecte, urgents primer (la targeta «Incidències»).
        if ($request->query('order') !== 'date') {
            $query->orderByRaw("CASE level WHEN 'URGENT' THEN 0 ELSE 1 END");
        }
        $query
            ->orderBy('recordDate', 'desc')
            ->orderBy('createdAt', 'desc')
            ->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (Alert $alert) => NoticeFeed::alert($alert, $role, $language)));
    }

    // Safata del menú Alertes/Avisos (avisos + recordatoris + consells barrejats per data), paginada. Sense ?patientId és la
    // llista plana de tot el que veu l'usuari (el pacient); amb ?patientId, la d'un sol pacient (el «Veure tot» d'un grup).
    public function feed(Request $request)
    {
        [$page, $perPage] = Paged::window($request);

        return response()->json(NoticeFeed::page($request, $request->query('status', 'pending'), $request->query('patientId'), $page, $perPage));
    }

    // Nutricionista: la safata agrupada per pacient. Pàgina de pacients (activitat més recent primer), cadascun amb el recompte
    // total i els seus 5 primers elements; la resta es demana amb feed?patientId=… en desplegar el grup.
    public function groups(Request $request)
    {
        [$page, $perPage] = Paged::window($request);

        return response()->json(NoticeFeed::groups($request, $request->query('status', 'pending'), $page, $perPage));
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
            ->map(fn (Alert $alert) => NoticeFeed::alert($alert, $role, $language));

        return response()->json($items);
    }

    private function baseQuery(Request $request)
    {
        return $this->own($request)->with(NoticeFeed::alertRelations());
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

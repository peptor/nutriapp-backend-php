<?php

namespace App\Http\Controllers;

use App\Models\AdviceNotice;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Consells disparats (reg_advice_notices): els d'avui per al bloc "Consells" de l'Avui del pacient, i
// l'històric d'una assignació per al grup "Consells" de l'Evolució (Notes del pacient / Incidències del
// nutricionista). Vegeu App\Support\AdviceGenerator i docs/com-funcionen-les-alertes.md.
class AdviceController extends Controller
{
    private function own(Request $request)
    {
        $user = $request->user();

        return AdviceNotice::whereHas('assignment.patient', fn ($q) => $user->role === 'PACIENT'
            ? $q->where('userId', $user->id)
            : $q->where('nutricionistaId', $user->id));
    }

    private function withRelations($query)
    {
        return $query->with(['assignment:id,patientId,templateId', 'assignment.template:id,name', 'assignment.patient:id,userId,photoUrl', 'assignment.patient.user:id,name']);
    }

    public function today(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $items = $this->withRelations($this->own($request))
            ->where('recordDate', $today)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (AdviceNotice $notice) => $this->format($notice));

        return response()->json($items);
    }

    // Històric dels consells: d'una assignació (Evolució, grup "Consells", amb ?assignmentId=) o de tots els del
    // pacient/nutricionista (menú Avisos, sense ?assignmentId=, barrejats amb avisos i recordatoris — secció 9).
    public function index(Request $request)
    {
        $query = $this->withRelations($this->own($request));
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }
        $items = $query
            ->orderBy('recordDate', 'desc')
            ->orderBy('createdAt', 'desc')
            ->limit(300)
            ->get()
            ->map(fn (AdviceNotice $notice) => $this->format($notice));

        return response()->json($items);
    }

    private function format(AdviceNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'message' => $notice->message,
            'fieldName' => $notice->fieldName,
            'assignmentId' => $notice->assignmentId,
            'recordDate' => $notice->recordDate->toDateString(),
            'routineName' => $notice->assignment->template->name,
            // Només calen al nutricionista, per agrupar per pacient (menú Alertes).
            'patientId' => $notice->assignment->patientId,
            'patientName' => $notice->assignment->patient->user->name,
            'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($notice->assignment->patient->photoUrl),
        ];
    }
}

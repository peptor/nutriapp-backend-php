<?php

namespace App\Http\Controllers;

use App\Models\ReminderNotice;
use App\Support\UrlHelper;
use Illuminate\Http\Request;

// Històric de recordatoris de registre ja enviats (reg_reminder_notices): d'una assignació (Evolució, grup
// "Recordatoris", amb ?assignmentId=) o de tots els del pacient/nutricionista (menú Avisos, sense ?assignmentId=,
// barrejats amb avisos i consells — secció 9). Vegeu App\Support\RegisterReminders i docs/com-funcionen-les-alertes.md.
class ReminderNoticesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ReminderNotice::whereHas('assignment.patient', fn ($q) => $user->role === 'PACIENT'
            ? $q->where('userId', $user->id)
            : $q->where('nutricionistaId', $user->id))
            ->with(['assignment:id,patientId,templateId', 'assignment.template:id,name', 'assignment.patient:id,userId,photoUrl', 'assignment.patient.user:id,name']);
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }

        $items = $query
            ->orderBy('recordDate', 'desc')
            ->limit(300)
            ->get()
            ->map(fn (ReminderNotice $notice) => [
                'id' => $notice->id,
                'assignmentId' => $notice->assignmentId,
                'period' => $notice->period,
                'recordDate' => $notice->recordDate->toDateString(),
                'routineName' => $notice->assignment->template->name,
                // Només calen al nutricionista, per agrupar per pacient (menú Alertes).
                'patientId' => $notice->assignment->patientId,
                'patientName' => $notice->assignment->patient->user->name,
                'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($notice->assignment->patient->photoUrl),
            ]);

        return response()->json($items);
    }
}

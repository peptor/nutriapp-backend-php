<?php

namespace App\Http\Controllers;

use App\Models\ReminderNotice;
use App\Support\NoticeFeed;
use App\Support\Paged;
use Illuminate\Http\Request;

// Històric de recordatoris de registre ja enviats (reg_reminder_notices) d'una assignació (Evolució, grup
// "Recordatoris", amb ?assignmentId=) o de tots els del pacient/nutricionista. PAGINAT (?page&perPage): { data, page,
// perPage, total, hasMore }. La safata del menú Avisos els barreja amb els avisos i els consells a NoticeFeed.
// Vegeu App\Support\RegisterReminders i docs/com-funcionen-les-alertes.md.
class ReminderNoticesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ReminderNotice::whereHas('assignment.patient', fn ($q) => $user->role === 'PACIENT'
            ? $q->where('userId', $user->id)
            : $q->where('nutricionistaId', $user->id))
            ->with(NoticeFeed::noticeRelations());
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }
        $query->orderBy('recordDate', 'desc')->orderBy('createdAt', 'desc')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (ReminderNotice $notice) => NoticeFeed::reminder($notice)));
    }
}

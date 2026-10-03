<?php

namespace App\Http\Controllers;

use App\Models\AdviceNotice;
use App\Support\NoticeFeed;
use App\Support\Paged;
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
        return $query->with(NoticeFeed::noticeRelations());
    }

    public function today(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $items = $this->withRelations($this->own($request))
            ->where('recordDate', $today)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (AdviceNotice $notice) => NoticeFeed::advice($notice));

        return response()->json($items);
    }

    // Històric dels consells: d'una assignació (Evolució, grup "Consells", amb ?assignmentId=) o de tots els del
    // pacient/nutricionista. PAGINAT (?page&perPage): { data, page, perPage, total, hasMore }. La safata del menú
    // Avisos els barreja amb avisos i recordatoris a NoticeFeed.
    public function index(Request $request)
    {
        $query = $this->withRelations($this->own($request));
        if ($request->filled('assignmentId')) {
            $query->where('assignmentId', $request->query('assignmentId'));
        }
        $query->orderBy('recordDate', 'desc')->orderBy('createdAt', 'desc')->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (AdviceNotice $notice) => NoticeFeed::advice($notice)));
    }
}

<?php

namespace App\Support;

use App\Models\AdviceNotice;
use App\Models\Alert;
use App\Models\Patient;
use App\Models\ReminderNotice;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Safata d'avisos del menú Alertes/Avisos: barreja avisos (reg_alerts), recordatoris (reg_reminder_notices) i consells
// (reg_advice_notices) en UNA llista ordenada per data, paginada al servidor (norma «Llistes llargues»; vegeu
// App\Support\Paged i docs/com-funcionen-les-alertes.md). Cada pàgina es resol amb una UNION d'ids —només columnes
// d'ordenació— i després s'hidraten només les files d'aquesta pàgina.
//   - Pestanya `pending`: avisos pendents + tots els recordatoris + tots els consells.
//   - Pestanyes `seen` / `resolved`: només avisos amb aquell estat (recordatoris i consells no tenen estat).
// El nutricionista la veu agrupada per pacient (groups()); el pacient, plana (page()).
class NoticeFeed
{
    /** Quants elements de cada pacient porta la pàgina de grups abans de «Veure tot (N)». */
    public const GROUP_PREVIEW = 5;

    private const ALERT_RELATIONS = [
        'assignment:id,patientId,templateId',
        'assignment.patient:id,userId,photoUrl,nutricionistaId',
        'assignment.patient.user:id,name',
        'assignment.patient.nutricionista:id,name',
        'assignment.template:id,name',
        'assignment.template.fields',
    ];

    private const NOTICE_RELATIONS = [
        'assignment:id,patientId,templateId',
        'assignment.template:id,name',
        'assignment.patient:id,userId,photoUrl',
        'assignment.patient.user:id,name',
    ];

    /** Filtre d'estat d'un avís segons el rol i la pestanya (les mateixes regles que AlertsController::index). */
    public static function applyAlertStatus($query, string $role, string $status, string $prefix = ''): void
    {
        if ($status === 'all') {
            return;
        }
        if ($role === 'PACIENT') {
            $status === 'seen' ? $query->whereNotNull($prefix.'patientSeenAt') : $query->whereNull($prefix.'patientSeenAt');
        } elseif ($status === 'pending') {
            $query->where($prefix.'status', 'OPEN');
        } elseif ($status === 'seen') {
            $query->where($prefix.'status', 'SEEN');
        } elseif ($status === 'resolved') {
            $query->where($prefix.'status', 'RESOLVED');
        }
    }

    /** Files (id, kind, recordDate, urgency, createdAt, patientId) de tot el que veu l'usuari a aquesta pestanya. */
    private static function union(Request $request, string $status, ?string $patientId): QueryBuilder
    {
        $user = $request->user();
        $source = function (string $table, string $columns) use ($user, $patientId) {
            $q = DB::table("$table as n")
                ->join('reg_routine_assignments as ra', 'ra.id', '=', 'n.assignmentId')
                ->join('sys_patients as p', 'p.id', '=', 'ra.patientId')
                ->selectRaw($columns);
            $user->role === 'PACIENT' ? $q->where('p.userId', $user->id) : $q->where('p.nutricionistaId', $user->id);
            if ($patientId) {
                $q->where('p.id', $patientId);
            }

            return $q;
        };

        $alerts = $source('reg_alerts', "n.id, 'alert' as kind, n.recordDate, CASE n.level WHEN 'URGENT' THEN 0 ELSE 1 END as urgency, n.createdAt, p.id as patientId");
        self::applyAlertStatus($alerts, $user->role, $status, 'n.');
        if ($status !== 'pending') {
            return $alerts;
        }

        return $alerts
            ->unionAll($source('reg_reminder_notices', "n.id, 'reminder' as kind, n.recordDate, 1 as urgency, n.createdAt, p.id as patientId"))
            ->unionAll($source('reg_advice_notices', "n.id, 'advice' as kind, n.recordDate, 1 as urgency, n.createdAt, p.id as patientId"));
    }

    /** Una pàgina de la llista plana (opcionalment d'un sol pacient): { data, page, perPage, total, hasMore }. */
    public static function page(Request $request, string $status, ?string $patientId, int $page, int $perPage): array
    {
        $union = self::union($request, $status, $patientId);
        $query = DB::query()->fromSub($union, 'f')->orderByDesc('recordDate')->orderBy('urgency')->orderByDesc('createdAt')->orderBy('id');
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return Paged::envelope(self::hydrate($rows, $request->user()->role, $request->user()->language), $page, $perPage, $total);
    }

    /** Pàgina de pacients amb elements a la pestanya (el de l'activitat més recent primer), cadascun amb la seva vista prèvia. */
    public static function groups(Request $request, string $status, int $page, int $perPage): array
    {
        $union = self::union($request, $status, null);
        $total = DB::query()->fromSub($union, 'f')->distinct()->count('patientId');
        $rows = DB::query()->fromSub($union, 'f')
            ->selectRaw('patientId, count(*) as n, max(recordDate) as latest')
            ->groupBy('patientId')
            ->orderByDesc('latest')
            ->orderBy('patientId')
            ->forPage($page, $perPage)
            ->get();

        $patients = Patient::with('user:id,name')->whereIn('id', $rows->pluck('patientId'))->get()->keyBy('id');
        $groups = $rows->map(fn ($row) => [
            'patientId' => $row->patientId,
            'patientName' => $patients[$row->patientId]->user->name,
            'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($patients[$row->patientId]->photoUrl),
            'count' => (int) $row->n,
            'latestDate' => (string) $row->latest,
            'items' => self::page($request, $status, $row->patientId, 1, self::GROUP_PREVIEW)['data'],
        ]);

        return Paged::envelope($groups, $page, $perPage, $total);
    }

    /** Converteix les files de la UNION en elements amb tot el detall, conservant l'ordre. */
    private static function hydrate($rows, string $role, ?string $language): array
    {
        $ids = fn (string $kind) => $rows->where('kind', $kind)->pluck('id');
        $alerts = Alert::with(self::ALERT_RELATIONS)->whereIn('id', $ids('alert'))->get()->keyBy('id');
        $reminders = ReminderNotice::with(self::NOTICE_RELATIONS)->whereIn('id', $ids('reminder'))->get()->keyBy('id');
        $advice = AdviceNotice::with(self::NOTICE_RELATIONS)->whereIn('id', $ids('advice'))->get()->keyBy('id');

        return $rows->map(fn ($row) => match ($row->kind) {
            'alert' => ['kind' => 'alert', ...self::alert($alerts[$row->id], $role, $language)],
            'reminder' => ['kind' => 'reminder', ...self::reminder($reminders[$row->id])],
            default => ['kind' => 'advice', ...self::advice($advice[$row->id])],
        })->all();
    }

    public static function alertRelations(): array
    {
        return self::ALERT_RELATIONS;
    }

    public static function noticeRelations(): array
    {
        return self::NOTICE_RELATIONS;
    }

    public static function alert(Alert $alert, string $role, ?string $language): array
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

    public static function reminder(ReminderNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'assignmentId' => $notice->assignmentId,
            'period' => $notice->period,
            'recordDate' => $notice->recordDate->toDateString(),
            'routineName' => $notice->assignment->template->name,
            // Només calen al nutricionista, per agrupar per pacient (menú Alertes).
            'patientId' => $notice->assignment->patientId,
            'patientName' => $notice->assignment->patient->user->name,
            'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($notice->assignment->patient->photoUrl),
        ];
    }

    public static function advice(AdviceNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'message' => $notice->message,
            'fieldName' => $notice->fieldName,
            'assignmentId' => $notice->assignmentId,
            'recordDate' => $notice->recordDate->toDateString(),
            'routineName' => $notice->assignment->template->name,
            'patientId' => $notice->assignment->patientId,
            'patientName' => $notice->assignment->patient->user->name,
            'patientPhotoUrl' => UrlHelper::toAbsoluteUrl($notice->assignment->patient->photoUrl),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Support\AppointmentAvailability;
use App\Support\AppointmentMessenger as Messenger;
use App\Support\Paged;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

// Visites del pacient: llista (properes / anteriors), detall, sol·licitud d'una visita nova (queda pendent que el
// nutricionista l'accepti o la rebutgi, vegeu AppointmentController::accept/reject), edició de les notes i anul·lació.
// El nutricionista rep un missatge a cada pas. Durada d'una visita sol·licitada: 30 minuts.
class PatientAppointmentsController extends Controller
{
    private const DURATION_MINUTES = 30;

    private const MAX_PENDING_REQUESTS = 3;

    private function mine(Request $request)
    {
        return Appointment::whereIn('patientId', Patient::where('userId', $request->user()->id)->select('id'))
            ->with(['nutricionista:id,name', 'nutricionista.nutricionistaProfile:userId,companyName,logoUrl']);
    }

    // Visites del pacient, PAGINADES al servidor (norma «Llistes llargues»): ?scope=upcoming (per defecte) | previous, ?page&perPage
    // → { data, page, perPage, total, hasMore, counts: { upcoming, previous } }.
    // Properes = pendents o confirmades que encara no han acabat, per data ascendent; anteriors = la resta (fetes, anul·lades i
    // rebutjades), per data descendent.
    public function index(Request $request)
    {
        $now = Messenger::nowWall()->toDateTimeString();
        $upcoming = fn ($q) => $q->whereIn('status', ['REQUESTED', 'CONFIRMED'])->where('endAt', '>=', $now);
        $counts = [
            'upcoming' => $upcoming($this->mine($request))->count(),
            'previous' => $this->mine($request)->whereNot(fn ($q) => $upcoming($q))->count(),
        ];

        $query = $this->mine($request);
        if ($request->query('scope') === 'previous') {
            $query->whereNot(fn ($q) => $upcoming($q))->orderByDesc('startAt');
        } else {
            $upcoming($query)->orderBy('startAt');
        }
        $query->orderBy('id');

        return response()->json(Paged::of($query, $request, fn (Appointment $a) => Messenger::presentForPatient($a)) + ['counts' => $counts]);
    }

    public function show(Request $request, string $id)
    {
        $appointment = $this->mine($request)->find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Visita no trobada'], 404);
        }

        return response()->json(Messenger::presentForPatient($appointment));
    }

    // Sol·licita una visita: crea la visita en estat REQUESTED i avisa el nutricionista amb un missatge.
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'nutricionistaId' => ['nullable', 'string', 'max:36'],
            // Hora de rellotge sense zona horària ("2026-10-08 10:00:00"), igual que el calendari del nutricionista.
            'startAt' => ['required', 'date_format:Y-m-d H:i:s'],
            'modality' => ['required', 'in:IN_PERSON,PHONE'],
            'reason' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'startAt.date_format' => 'La data i l\'hora no són vàlides',
            'startAt.required' => 'Tria el dia i l\'hora de la visita',
        ]);
        $start = Carbon::parse($data['startAt'], 'UTC');
        if ($start->lte(Messenger::nowWall())) {
            throw ValidationException::withMessages(['startAt' => ['Tria una data i hora futures']]);
        }

        $profiles = Patient::where('userId', $user->id)->get();
        $patient = isset($data['nutricionistaId'])
            ? $profiles->firstWhere('nutricionistaId', $data['nutricionistaId'])
            : ($profiles->count() === 1 ? $profiles->first() : null);
        if (! $patient) {
            return response()->json(['error' => 'Indica a quin nutricionista vols demanar la visita'], 422);
        }
        $nutri = User::where('id', $patient->nutricionistaId)->whereNull('deletedAt')->first();
        if (! $nutri) {
            return response()->json(['error' => 'Aquest nutricionista ja no està disponible'], 409);
        }

        $pending = Appointment::where('patientId', $patient->id)->where('status', 'REQUESTED')->where('endAt', '>=', Messenger::nowWall())->get();
        if ($pending->count() >= self::MAX_PENDING_REQUESTS) {
            return response()->json(['error' => 'Ja tens '.self::MAX_PENDING_REQUESTS.' sol·licituds pendents. Espera que el nutricionista les respongui.'], 409);
        }
        if ($pending->contains(fn (Appointment $a) => $a->startAt->equalTo($start))) {
            return response()->json(['error' => 'Ja has demanat una visita per a aquesta data i hora'], 409);
        }

        // Si la franja ja està ocupada o el nutricionista no hi treballa (festiu, absència, vacances, fora d'horari), la
        // sol·licitud es rebutja sola: el pacient rep directament la resposta «Aquesta data està ja ocupada.» i el
        // nutricionista no en rep cap avís (no hi ha res a decidir).
        $end = $start->copy()->addMinutes(self::DURATION_MINUTES);
        $available = AppointmentAvailability::isAvailable($nutri->id, $start, $end);

        $appointment = Appointment::create([
            'patientId' => $patient->id,
            'nutricionistaId' => $nutri->id,
            'startAt' => $start,
            'endAt' => $end,
            'reason' => isset($data['reason']) && trim($data['reason']) !== '' ? trim($data['reason']) : null,
            'modality' => $data['modality'],
            'patientNotes' => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
            'status' => $available ? 'REQUESTED' : 'REJECTED',
            'requestedBy' => 'PATIENT',
        ]);

        if (! $available) {
            Messenger::send($appointment, $nutri, $user, 'Sol·licitud de visita · '.Messenger::dateText($start), '<p>Aquesta data està ja ocupada.</p>');

            return response()->json(Messenger::presentForPatient($appointment->load('nutricionista.nutricionistaProfile')), 201);
        }

        $body = '<p><strong>'.e($user->name).'</strong> sol·licita una visita el '.e(Messenger::dateText($start)).' a les '.e(Messenger::timeText($start)).'.</p>'
            .'<p>Modalitat: '.($data['modality'] === 'PHONE' ? 'telefònica (truques tu al pacient)' : 'consulta presencial').'.</p>'
            .($appointment->reason ? '<p>Motiu de la visita: '.e($appointment->reason).'.</p>' : '')
            .($appointment->patientNotes ? '<p>Notes: '.e($appointment->patientNotes).'</p>' : '');
        Messenger::send($appointment, $user, $nutri, 'Sol·licitud de visita · '.Messenger::dateText($start), $body, true);

        return response()->json(Messenger::presentForPatient($appointment->load('nutricionista.nutricionistaProfile')), 201);
    }

    // Només les notes són editables pel pacient, i només en una visita pendent o confirmada que encara no ha passat.
    public function update(Request $request, string $id)
    {
        $data = $request->validate(['patientNotes' => ['nullable', 'string', 'max:2000']]);
        $appointment = $this->mine($request)->find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Visita no trobada'], 404);
        }
        if (! $this->isOpen($appointment)) {
            return response()->json(['error' => 'Aquesta visita ja no es pot modificar'], 409);
        }

        $notes = isset($data['patientNotes']) ? trim($data['patientNotes']) : '';
        $appointment->update(['patientNotes' => $notes !== '' ? $notes : null]);

        return response()->json(Messenger::presentForPatient($appointment->fresh(['nutricionista.nutricionistaProfile'])));
    }

    // Anul·la la visita (queda a «Anteriors» com a anul·lada, sense esborrar-la: així hi ha rastre) i avisa el nutricionista.
    public function cancel(Request $request, string $id)
    {
        $appointment = $this->mine($request)->find($id);
        if (! $appointment) {
            return response()->json(['error' => 'Visita no trobada'], 404);
        }
        if (! $this->isOpen($appointment)) {
            return response()->json(['error' => 'Aquesta visita ja no es pot anul·lar'], 409);
        }

        $appointment->update(['status' => 'CANCELLED', 'cancelledAt' => now()]);

        $nutri = User::find($appointment->nutricionistaId);
        if ($nutri) {
            $body = '<p><strong>'.e($request->user()->name).'</strong> ha anul·lat la visita del '.e(Messenger::dateText($appointment->startAt)).' a les '.e(Messenger::timeText($appointment->startAt)).'.</p>';
            Messenger::send($appointment, $request->user(), $nutri, 'Visita anul·lada · '.Messenger::dateText($appointment->startAt), $body);
        }

        return response()->json(Messenger::presentForPatient($appointment->fresh(['nutricionista.nutricionistaProfile'])));
    }

    private function isOpen(Appointment $appointment): bool
    {
        return in_array($appointment->status, ['REQUESTED', 'CONFIRMED'], true) && $appointment->endAt->gte(Messenger::nowWall());
    }
}

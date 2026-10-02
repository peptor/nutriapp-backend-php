<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Support\AppointmentMessenger as Messenger;
use App\Support\UrlHelper;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    // Cites del nutricionista dins un rang de dates (per pintar la graella mensual del calendari).
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $appointments = Appointment::confirmed()->where('nutricionistaId', $request->user()->id)
            ->whereBetween('startAt', [$request->query('from'), $request->query('to')])
            ->with('patient.user:id,name')
            ->orderBy('startAt')
            ->get();

        $result = $appointments->map(function (Appointment $a) {
            $data = $a->only(['id', 'patientId', 'startAt', 'endAt', 'reason', 'modality', 'location']);
            $data['patientName'] = $a->patient->user->name ?? null;
            $data['patientPhotoUrl'] = UrlHelper::toAbsoluteUrl($a->patient->photoUrl ?? null);

            return $data;
        });

        return response()->json($result);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $patient = Patient::find($data['patientId']);
        if (! $patient || $patient->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Pacient no trobat'], 404);
        }

        $appointment = Appointment::create([
            'patientId' => $data['patientId'],
            'nutricionistaId' => $request->user()->id,
            'startAt' => $data['startAt'],
            'endAt' => $data['endAt'],
            'reason' => $data['reason'] ?? null,
            'modality' => $data['modality'] ?? 'IN_PERSON',
            'location' => $data['location'] ?? null,
        ]);

        // El pacient rep el mateix missatge que quan s'accepta una sol·licitud: «Visita confirmada el …». Si cal, el
        // nutricionista l'anul·larà després (i el pacient en rebrà l'avís).
        $this->tellPatient($appointment, 'Visita confirmada · '.Messenger::dateText($appointment->startAt),
            '<p>Visita confirmada el '.e(Messenger::dateText($appointment->startAt)).' a les '.e(Messenger::timeText($appointment->startAt)).'.</p>', $request->user());

        return response()->json($appointment, 201);
    }

    public function update(UpdateAppointmentRequest $request, string $id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment || $appointment->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Cita no trobada'], 404);
        }

        $data = $request->validated();
        if (isset($data['patientId']) && $data['patientId'] !== $appointment->patientId) {
            $patient = Patient::find($data['patientId']);
            if (! $patient || $patient->nutricionistaId !== $request->user()->id) {
                return response()->json(['error' => 'Pacient no trobat'], 404);
            }
        }

        $before = $appointment->startAt->copy();
        $appointment->update($data);

        // Si una visita confirmada canvia de dia o d'hora, el pacient en rep l'avís (si no, el missatge de confirmació
        // anterior quedaria desfasat).
        if ($appointment->status === 'CONFIRMED' && $appointment->startAt->ne($before)) {
            $this->tellPatient($appointment, 'Visita canviada · '.Messenger::dateText($appointment->startAt),
                '<p>La teva visita s\'ha canviat: nova data, el '.e(Messenger::dateText($appointment->startAt)).' a les '.e(Messenger::timeText($appointment->startAt)).'.</p>', $request->user());
        }

        return response()->json($appointment);
    }

    // Accepta una sol·licitud de visita del pacient: queda confirmada i el pacient rep «Visita confirmada el …».
    // Si el nutricionista ja té una altra visita confirmada en aquesta franja, no es pot acceptar (ha de rebutjar-la).
    public function accept(Request $request, string $id)
    {
        $appointment = $this->pendingRequest($request, $id);
        if ($appointment instanceof \Illuminate\Http\JsonResponse) {
            return $appointment;
        }

        $overlaps = Appointment::confirmed()
            ->where('nutricionistaId', $request->user()->id)
            ->where('id', '!=', $appointment->id)
            ->where('startAt', '<', $appointment->endAt)
            ->where('endAt', '>', $appointment->startAt)
            ->exists();
        if ($overlaps) {
            return response()->json(['error' => "Ja tens una altra visita en aquesta franja. No es pot acceptar: rebutja la sol·licitud."], 409);
        }

        $appointment->update([
            'status' => 'CONFIRMED',
            'location' => $appointment->modality === 'IN_PERSON' ? Messenger::locationOf($appointment) : null,
        ]);

        $patientUser = User::find($appointment->patient->userId);
        if ($patientUser) {
            $body = '<p>Visita confirmada el '.e(Messenger::dateText($appointment->startAt)).' a les '.e(Messenger::timeText($appointment->startAt)).'.</p>';
            Messenger::send($appointment, $request->user(), $patientUser, 'Sol·licitud de visita', $body);
        }

        return response()->json(Messenger::presentForMessage($appointment->fresh()));
    }

    // Rebutja una sol·licitud de visita: el pacient rep «Aquesta data està ja ocupada.».
    public function reject(Request $request, string $id)
    {
        $appointment = $this->pendingRequest($request, $id);
        if ($appointment instanceof \Illuminate\Http\JsonResponse) {
            return $appointment;
        }

        $appointment->update(['status' => 'REJECTED']);

        $patientUser = User::find($appointment->patient->userId);
        if ($patientUser) {
            Messenger::send($appointment, $request->user(), $patientUser, 'Sol·licitud de visita', '<p>Aquesta data està ja ocupada.</p>');
        }

        return response()->json(Messenger::presentForMessage($appointment->fresh()));
    }

    // Sol·licitud pendent d'aquest nutricionista (o la resposta d'error adequada).
    private function pendingRequest(Request $request, string $id)
    {
        $appointment = Appointment::with('patient')->find($id);
        if (! $appointment || $appointment->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Cita no trobada'], 404);
        }
        if ($appointment->status !== 'REQUESTED') {
            return response()->json(['error' => 'Aquesta sol·licitud ja s\'ha respost o el pacient l\'ha anul·lada'], 409);
        }

        return $appointment;
    }

    public function destroy(Request $request, string $id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment || $appointment->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Cita no trobada'], 404);
        }

        // Una visita confirmada que encara no ha passat no s'esborra: queda anul·lada (el pacient la veu a «Anteriors»)
        // i rep un missatge. Qualsevol altra (passada, pendent...) s'esborra com abans.
        if ($appointment->status === 'CONFIRMED' && $appointment->endAt->gte(Messenger::nowWall())) {
            $appointment->update(['status' => 'CANCELLED', 'cancelledAt' => now()]);
            $this->tellPatient($appointment, 'Visita anul·lada · '.Messenger::dateText($appointment->startAt),
                '<p>La teva visita del '.e(Messenger::dateText($appointment->startAt)).' a les '.e(Messenger::timeText($appointment->startAt)).' ha estat anul·lada.</p>', $request->user());
        } else {
            $appointment->delete();
        }

        return response()->json(['success' => true]);
    }

    // Missatge del nutricionista al pacient dins el fil de la visita.
    private function tellPatient(Appointment $appointment, string $subject, string $bodyHtml, User $nutricionista): void
    {
        $patientUser = User::find(Patient::where('id', $appointment->patientId)->value('userId'));
        if ($patientUser && ! $patientUser->deletedAt) {
            Messenger::send($appointment, $nutricionista, $patientUser, $subject, $bodyHtml);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
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

        $appointments = Appointment::where('nutricionistaId', $request->user()->id)
            ->whereBetween('startAt', [$request->query('from'), $request->query('to')])
            ->with('patient.user:id,name')
            ->orderBy('startAt')
            ->get();

        $result = $appointments->map(function (Appointment $a) {
            $data = $a->only(['id', 'patientId', 'startAt', 'endAt', 'reason']);
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
        ]);

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

        $appointment->update($data);

        return response()->json($appointment);
    }

    public function destroy(Request $request, string $id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment || $appointment->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Cita no trobada'], 404);
        }

        $appointment->delete();

        return response()->json(['success' => true]);
    }
}

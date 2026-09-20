<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleExceptionRequest;
use App\Http\Requests\UpdateScheduleExceptionRequest;
use App\Http\Requests\UpdateSchedulePatternRequest;
use App\Http\Requests\UpdateScheduleSlotsRequest;
use App\Models\NutricionistaProfile;
use App\Models\ScheduleException;
use App\Models\ScheduleSlot;
use App\Support\ScheduleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    // Patró d'horari (setmana única o A/B) + tots els trams horaris configurats.
    public function show(Request $request)
    {
        $nutricionistaId = $request->user()->id;
        $profile = NutricionistaProfile::firstOrCreate(['userId' => $nutricionistaId]);
        $slots = ScheduleSlot::where('nutricionistaId', $nutricionistaId)
            ->orderBy('dayOfWeek')
            ->orderBy('startTime')
            ->get(['id', 'week', 'dayOfWeek', 'startTime', 'endTime']);

        return response()->json([
            'mode' => $profile->scheduleMode,
            'cycleStartDate' => $profile->scheduleCycleStartDate,
            'cycleStartWeek' => $profile->scheduleCycleStartWeek,
            'slots' => $slots,
        ]);
    }

    // Actualitza el mode (WEEKLY/BIWEEKLY) i, si cal, l'àncora del cicle de setmanes A/B.
    public function updatePattern(UpdateSchedulePatternRequest $request)
    {
        $data = $request->validated();
        $profile = NutricionistaProfile::firstOrCreate(['userId' => $request->user()->id]);
        $profile->update([
            'scheduleMode' => $data['mode'],
            'scheduleCycleStartDate' => $data['mode'] === 'BIWEEKLY' ? $data['cycleStartDate'] : null,
            'scheduleCycleStartWeek' => $data['mode'] === 'BIWEEKLY' ? $data['cycleStartWeek'] : 'A',
        ]);

        return response()->json(['success' => true]);
    }

    // Reemplaça tots els trams horaris del nutricionista pels que arriben (l'editor sempre
    // envia el conjunt sencer, més senzill que sincronitzar canvis un a un).
    public function updateSlots(UpdateScheduleSlotsRequest $request)
    {
        $nutricionistaId = $request->user()->id;
        $slots = $request->validated()['slots'];

        DB::transaction(function () use ($nutricionistaId, $slots) {
            ScheduleSlot::where('nutricionistaId', $nutricionistaId)->delete();
            foreach ($slots as $slot) {
                ScheduleSlot::create([
                    'nutricionistaId' => $nutricionistaId,
                    'week' => $slot['week'] ?? null,
                    'dayOfWeek' => $slot['dayOfWeek'],
                    'startTime' => $slot['startTime'],
                    'endTime' => $slot['endTime'],
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function exceptionsIndex(Request $request)
    {
        $exceptions = ScheduleException::where('nutricionistaId', $request->user()->id)
            ->orderBy('date')
            ->get();

        return response()->json($exceptions);
    }

    public function exceptionsStore(StoreScheduleExceptionRequest $request)
    {
        $data = $request->validated();
        $nutricionistaId = $request->user()->id;

        if (ScheduleException::where('nutricionistaId', $nutricionistaId)->where('date', $data['date'])->exists()) {
            return response()->json(['error' => 'Ja hi ha una excepció per aquesta data.'], 409);
        }

        $exception = ScheduleException::create([
            'nutricionistaId' => $nutricionistaId,
            'date' => $data['date'],
            'type' => $data['type'],
            'startTime' => $data['type'] === 'HORARI_ESPECIAL' ? $data['startTime'] : null,
            'endTime' => $data['type'] === 'HORARI_ESPECIAL' ? $data['endTime'] : null,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($exception, 201);
    }

    public function exceptionsUpdate(UpdateScheduleExceptionRequest $request, string $id)
    {
        $nutricionistaId = $request->user()->id;
        $exception = ScheduleException::where('nutricionistaId', $nutricionistaId)->find($id);
        if (! $exception) {
            return response()->json(['error' => 'Excepció no trobada'], 404);
        }

        $data = $request->validated();

        if (
            $data['date'] !== $exception->date->format('Y-m-d')
            && ScheduleException::where('nutricionistaId', $nutricionistaId)->where('date', $data['date'])->exists()
        ) {
            return response()->json(['error' => 'Ja hi ha una excepció per aquesta data.'], 409);
        }

        $exception->update([
            'date' => $data['date'],
            'type' => $data['type'],
            'startTime' => $data['type'] === 'HORARI_ESPECIAL' ? $data['startTime'] : null,
            'endTime' => $data['type'] === 'HORARI_ESPECIAL' ? $data['endTime'] : null,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($exception);
    }

    public function exceptionsDestroy(Request $request, string $id)
    {
        $exception = ScheduleException::where('nutricionistaId', $request->user()->id)->find($id);
        if (! $exception) {
            return response()->json(['error' => 'Excepció no trobada'], 404);
        }
        $exception->delete();

        return response()->json(['success' => true]);
    }

    // Per a cada dia entre `from` i `to`: si treballa, amb quins trams, i si és festiu,
    // absència, vacances o horari especial. Ho fan servir tant la vista prèvia de
    // Configuració com el calendari de cites (per marcar els dies que no treballa).
    public function resolved(Request $request)
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        return response()->json(ScheduleResolver::resolveRange($request->user()->id, $request->query('from'), $request->query('to')));
    }
}

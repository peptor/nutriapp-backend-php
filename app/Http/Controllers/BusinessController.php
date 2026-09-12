<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessProfileRequest;
use App\Models\NutricionistaProfile;
use App\Models\Patient;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessController extends Controller
{
    private const ALLOWED_LOGO_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    // Dades de l'empresa del nutricionista autenticat
    public function show(Request $request)
    {
        $profile = NutricionistaProfile::where('userId', $request->user()->id)->first();

        return response()->json([
            'companyName' => $profile?->companyName,
            'taxId' => $profile?->taxId,
            'address' => $profile?->address,
            'postalCode' => $profile?->postalCode,
            'city' => $profile?->city,
            'phone' => $profile?->phone,
            'logoUrl' => UrlHelper::toAbsoluteUrl($profile?->logoUrl),
        ]);
    }

    // Actualitza les dades de l'empresa i, opcionalment, el logo (multipart/form-data)
    public function update(BusinessProfileRequest $request)
    {
        $data = $request->validated();
        $existing = NutricionistaProfile::where('userId', $request->user()->id)->first();

        $logoUrl = $existing?->logoUrl;
        $file = $request->file('logo');
        if ($file) {
            $ext = self::ALLOWED_LOGO_MIME[$file->getMimeType()] ?? null;
            if (! $ext) {
                return response()->json(['error' => "Format d'imatge no vàlid. Usa PNG, JPG, WEBP o SVG."], 400);
            }
            if ($file->getSize() > 2 * 1024 * 1024) {
                return response()->json(['error' => 'La imatge no pot superar els 2 MB'], 400);
            }
            if ($existing?->logoUrl) {
                Storage::disk('public')->delete($existing->logoUrl);
            }
            $filename = $request->user()->id.'-'.now()->getTimestampMs().'.'.$ext;
            $logoUrl = $file->storeAs('logos', $filename, 'public');
        }

        $profile = NutricionistaProfile::updateOrCreate(
            ['userId' => $request->user()->id],
            [...$data, 'logoUrl' => $logoUrl],
        );

        return response()->json([
            'companyName' => $profile->companyName,
            'taxId' => $profile->taxId,
            'address' => $profile->address,
            'postalCode' => $profile->postalCode,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'logoUrl' => UrlHelper::toAbsoluteUrl($profile->logoUrl),
        ]);
    }

    // Elimina només el logo, mantenint la resta de dades de l'empresa
    public function deleteLogo(Request $request)
    {
        $existing = NutricionistaProfile::where('userId', $request->user()->id)->first();
        if (! $existing?->logoUrl) {
            return response()->json(['message' => 'No hi havia cap logo']);
        }
        Storage::disk('public')->delete($existing->logoUrl);
        $existing->update(['logoUrl' => null]);

        return response()->json(['message' => 'Logo eliminat']);
    }

    // El pacient consulta el logo/nom de l'empresa de cadascun dels seus nutricionistes
    // (pot estar-hi associat a més d'un alhora, un per cada fila Patient).
    public function myNutricionistes(Request $request)
    {
        $patients = Patient::where('userId', $request->user()->id)
            ->with('nutricionista.nutricionistaProfile:userId,companyName,logoUrl')
            ->get();

        return response()->json($patients->map(fn (Patient $p) => [
            'patientId' => $p->id,
            'nutricionistaId' => $p->nutricionistaId,
            'name' => $p->nutricionista->name,
            'companyName' => $p->nutricionista->nutricionistaProfile?->companyName,
            'logoUrl' => UrlHelper::toAbsoluteUrl($p->nutricionista->nutricionistaProfile?->logoUrl),
        ]));
    }
}

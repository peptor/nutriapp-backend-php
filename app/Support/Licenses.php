<?php

namespace App\Support;

use App\Models\NutricionistaLicense;
use App\Models\Patient;
use App\Models\RoutineTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Plans dels nutricionistes. El pla es DERIVA de l'historial de llicències (reg_nutricionista_licenses), no es
// guarda: no cal cap tasca que "caduqui" res. "EvoPro" = alguna llicència vigent avui; "EvoDemo" = cap.
// Una llicència és vigent si no està revocada, ja ha començat i no ha caducat (`endsAt` NULL = sense caducitat;
// el dia `endsAt` encara és vigent). Els pagaments (mensuals/anuals) els crea App\Support\Billing (webhook invoice.paid) amb
// source PAYMENT i la data de fi del període pagat; els cobraments consecutius es van encadenant.
class Licenses
{
    public const PRO = 'EvoPro';

    public const DEMO = 'EvoDemo';

    // Límits d'EvoDemo (02/10/2026): pacients vinculats i plantilles de rutina pròpies. Passar-ne no esborra res:
    // només impedeix crear-ne de nous.
    public const DEMO_MAX_PATIENTS = 5;

    public const DEMO_MAX_TEMPLATES = 3;

    /** @param  iterable<NutricionistaLicense>  $licenses  Les llicències d'un nutricionista (qualsevol estat). */
    public static function summary(iterable $licenses): array
    {
        $today = Carbon::today()->toDateString();
        $active = Collection::make($licenses)->filter(fn (NutricionistaLicense $l) => self::isActive($l, $today));

        if ($active->isEmpty()) {
            return ['code' => self::DEMO, 'until' => null, 'indefinite' => false];
        }
        // Si n'hi ha alguna sense caducitat, el pla no caduca; si no, mana la data de fi més llunyana.
        $indefinite = $active->contains(fn (NutricionistaLicense $l) => $l->endsAt === null);

        return [
            'code' => self::PRO,
            'until' => $indefinite ? null : $active->max(fn (NutricionistaLicense $l) => $l->endsAt->toDateString()),
            'indefinite' => $indefinite,
        ];
    }

    public static function isActive(NutricionistaLicense $license, ?string $today = null): bool
    {
        $today ??= Carbon::today()->toDateString();

        return $license->revokedAt === null
            && $license->startsAt->toDateString() <= $today
            && ($license->endsAt === null || $license->endsAt->toDateString() >= $today);
    }

    public static function planOf(User $nutricionista): array
    {
        return self::summary($nutricionista->licenses()->get());
    }

    public static function isPro(User $nutricionista): bool
    {
        return self::planOf($nutricionista)['code'] === self::PRO;
    }

    // Un pacient té "EvoPro" si algun dels seus nutricionistes en té (vegeu "Els meus aliments" i l'Evolució).
    public static function patientHasPro(User $patientUser): bool
    {
        return User::whereIn('id', Patient::where('userId', $patientUser->id)->select('nutricionistaId'))
            ->with('licenses')->get()
            ->contains(fn (User $n) => self::summary($n->licenses)['code'] === self::PRO);
    }

    public static function patientsUsed(User $nutricionista): int
    {
        return Patient::where('nutricionistaId', $nutricionista->id)
            ->whereHas('user', fn ($q) => $q->whereNull('deletedAt'))
            ->count();
    }

    public static function templatesUsed(User $nutricionista): int
    {
        return RoutineTemplate::where('createdById', $nutricionista->id)->count();
    }

    // Ús i límits del nutricionista: `max` és null si és EvoPro (sense límit).
    public static function usage(User $nutricionista): array
    {
        $demo = ! self::isPro($nutricionista);

        return [
            'patients' => ['used' => self::patientsUsed($nutricionista), 'max' => $demo ? self::DEMO_MAX_PATIENTS : null],
            'templates' => ['used' => self::templatesUsed($nutricionista), 'max' => $demo ? self::DEMO_MAX_TEMPLATES : null],
        ];
    }

    // Missatge d'error si un nutricionista EvoDemo ja no pot afegir un pacient / crear una rutina; null si pot.
    public static function patientLimitError(User $nutricionista): ?string
    {
        if (! self::isPro($nutricionista) && self::patientsUsed($nutricionista) >= self::DEMO_MAX_PATIENTS) {
            return 'El pla EvoDemo permet com a màxim '.self::DEMO_MAX_PATIENTS.' pacients. Passa a EvoPro per afegir-ne més.';
        }

        return null;
    }

    public static function templateLimitError(User $nutricionista): ?string
    {
        if (! self::isPro($nutricionista) && self::templatesUsed($nutricionista) >= self::DEMO_MAX_TEMPLATES) {
            return 'El pla EvoDemo permet com a màxim '.self::DEMO_MAX_TEMPLATES.' rutines pròpies. Passa a EvoPro per crear-ne més.';
        }

        return null;
    }

    // Deixa el nutricionista a EvoPro fins a `$endsAt` (inclosa): revoca les llicències vigents i en crea una de nova,
    // així la data indicada és sempre la que mana (també per avançar-la). L'historial es conserva.
    public static function grant(User $nutricionista, string $endsAt, string $source, ?string $createdById = null, array $extra = []): NutricionistaLicense
    {
        self::revokeActive($nutricionista);

        return NutricionistaLicense::create([
            'nutricionistaId' => $nutricionista->id,
            'startsAt' => Carbon::today()->toDateString(),
            'endsAt' => $endsAt,
            'billingPeriod' => $extra['billingPeriod'] ?? 'MANUAL',
            'source' => $source,
            'createdById' => $createdById,
            ...array_intersect_key($extra, array_flip(['amountCents', 'currency', 'paymentRef', 'note'])),
        ]);
    }

    // Passa el nutricionista a EvoDemo: revoca totes les llicències vigents (queden a l'historial).
    public static function revokeActive(User $nutricionista): void
    {
        $today = Carbon::today()->toDateString();
        $nutricionista->licenses()->get()
            ->filter(fn (NutricionistaLicense $l) => self::isActive($l, $today))
            ->each(fn (NutricionistaLicense $l) => $l->update(['revokedAt' => now()]));
    }
}

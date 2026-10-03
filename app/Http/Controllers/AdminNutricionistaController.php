<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\NutricionistaProfile;
use App\Models\Patient;
use App\Models\User;
use App\Support\Licenses;
use App\Support\Paged;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Fitxa de client d'un nutricionista per a l'administrador (CRM): dades, pla i llicències, ús, pacients, factures i
// notes internes. Només lectura excepte les notes (que només veu l'administrador).
class AdminNutricionistaController extends Controller
{
    // Quants elements es porten a la fitxa abans de «Veure tot» (que ja és paginat).
    public const PREVIEW = 5;

    // Quants pacients es porten a la fitxa abans de «Veure tot».
    public const PATIENT_PREVIEW = 6;

    private function find(string $id): ?User
    {
        return User::where('role', 'NUTRICIONISTA')->find($id);
    }

    public function overview(string $id)
    {
        $user = $this->find($id);
        if (! $user) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }
        $profile = NutricionistaProfile::where('userId', $id)->first();
        $licenses = $user->licenses()->orderByDesc('startsAt')->orderByDesc('createdAt')->get(); // el pla es calcula amb totes
        $invoiceTotals = Invoice::where('nutricionistaId', $id)->selectRaw('COUNT(*) as n, COALESCE(SUM(totalCents),0) as total')->first();
        $patientsTotal = (clone $this->patientsQuery($id))->count();
        $patientsWithFollowUp = (clone $this->patientsQuery($id))->whereExists(fn ($q) => $q->select(DB::raw(1))->from('reg_routine_assignments as ra')->whereColumn('ra.patientId', 'sys_patients.id')->where('ra.status', 'ACTIVE'))->count();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'createdAt', 'deletedAt']),
            'company' => [
                'companyName' => $profile?->companyName,
                'taxId' => $profile?->taxId,
                'address' => $profile?->address,
                'postalCode' => $profile?->postalCode,
                'city' => $profile?->city,
                'phone' => $profile?->phone,
                'collegiateNumber' => $profile?->collegiateNumber,
            ],
            'hasStripe' => (bool) $profile?->stripeCustomerId,
            'plan' => Licenses::summary($licenses),
            'usage' => Licenses::usage($user),
            // Vista prèvia (les 5 més recents) + total: «Veure tot» demana la llista paginada (licenses()).
            'licenses' => ['count' => $licenses->count(), 'items' => $licenses->take(self::PREVIEW)->map(fn ($l) => $this->licenseRow($l))->values()],
            // Vista prèvia (els 6 primers, els que tenen més rutines actives) + total: «Veure tot» demana la llista paginada (patients()).
            'patients' => [
                'count' => $patientsTotal,
                'withFollowUp' => $patientsWithFollowUp,
                'items' => $this->patientsQuery($id)->orderByDesc('activeRoutines')->orderBy('u.name')->orderBy('sys_patients.id')->limit(self::PATIENT_PREVIEW)->get()->map(fn ($p) => $this->patientRow($p))->values(),
            ],
            'invoices' => [
                'count' => (int) $invoiceTotals->n,
                'totalCents' => (int) $invoiceTotals->total,
                'items' => $this->invoicesQuery($id)->limit(self::PREVIEW)->get()->map(fn (Invoice $i) => $this->invoiceRow($i))->values(),
            ],
            'adminNotes' => $profile?->adminNotes,
        ]);
    }

    private function licenseRow($l): array
    {
        return [
            'id' => $l->id,
            'startsAt' => $l->startsAt->toDateString(),
            'endsAt' => $l->endsAt?->toDateString(),
            'billingPeriod' => $l->billingPeriod,
            'source' => $l->source,
            'amountCents' => $l->amountCents,
            'revoked' => $l->revokedAt !== null,
            'note' => $l->note,
            'reminderSent' => $l->expiryReminderSentAt !== null,
        ];
    }

    private function invoiceRow(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'number' => $i->number,
            'issuedAt' => $i->issuedAt->toDateString(),
            'concept' => $i->concept,
            'totalCents' => $i->totalCents,
            'currency' => $i->currency,
        ];
    }

    private function invoicesQuery(string $id)
    {
        return Invoice::where('nutricionistaId', $id)->orderByDesc('issuedAt')->orderByDesc('year')->orderByDesc('sequence');
    }

    // Pacients (amb el seu usuari actiu) d'un nutricionista, amb el nombre de rutines actives.
    private function patientsQuery(string $id)
    {
        return Patient::query()
            ->join('sys_users as u', 'u.id', '=', 'sys_patients.userId')
            ->whereNull('u.deletedAt')
            ->where('sys_patients.nutricionistaId', $id)
            ->select('sys_patients.id', 'sys_patients.userId', 'sys_patients.photoUrl', 'sys_patients.createdAt', 'u.name', 'u.email', 'u.role', 'u.createdAt as userCreatedAt')
            ->selectSub(fn ($q) => $q->from('reg_routine_assignments as ra')->selectRaw('count(*)')->whereColumn('ra.patientId', 'sys_patients.id')->where('ra.status', 'ACTIVE'), 'activeRoutines');
    }

    private function patientRow($p): array
    {
        return [
            'id' => $p->id,
            'userId' => $p->userId,
            'name' => $p->name,
            'email' => $p->email,
            'role' => $p->role,
            'createdAt' => $p->userCreatedAt,
            'photoUrl' => UrlHelper::toAbsoluteUrl($p->photoUrl),
            'activeRoutines' => (int) $p->activeRoutines,
        ];
    }

    // Pacients d'un nutricionista, paginats (?page&perPage). ?sort=routines (els que tenen més rutines actives primer, per a la
    // fitxa de client) | name (per defecte, alfabètic, per a la pantalla d'usuaris).
    public function patients(Request $request, string $id)
    {
        if (! $this->find($id)) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }
        $query = $this->patientsQuery($id);
        if ($request->query('sort') === 'routines') {
            $query->orderByDesc('activeRoutines');
        }
        $query->orderBy('u.name')->orderBy('sys_patients.id');

        return response()->json(Paged::of($query, $request, fn ($p) => $this->patientRow($p), 10));
    }

    // Llicències d'un nutricionista, paginades (?page&perPage): vegeu App\Support\Paged.
    public function licenses(Request $request, string $id)
    {
        $user = $this->find($id);
        if (! $user) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }

        return response()->json(Paged::of($user->licenses()->orderByDesc('startsAt')->orderByDesc('createdAt'), $request, fn ($l) => $this->licenseRow($l)));
    }

    // Factures d'un nutricionista, paginades (?page&perPage).
    public function invoices(Request $request, string $id)
    {
        if (! $this->find($id)) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }

        return response()->json(Paged::of($this->invoicesQuery($id), $request, fn (Invoice $i) => $this->invoiceRow($i)));
    }

    // Notes internes (només visibles per als administradors).
    public function updateNotes(Request $request, string $id)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        if (! $this->find($id)) {
            return response()->json(['error' => 'Nutricionista no trobat'], 404);
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        NutricionistaProfile::updateOrCreate(['userId' => $id], ['adminNotes' => $notes !== '' ? $notes : null]);

        return response()->json(['adminNotes' => $notes !== '' ? $notes : null]);
    }
}

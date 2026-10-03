<?php

namespace App\Console\Commands;

use App\Mail\LicenseExpiringMail;
use App\Models\NutricionistaLicense;
use App\Models\NutricionistaProfile;
use App\Models\User;
use App\Support\Licenses;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

// Recordatori de caducitat: quan a un nutricionista EvoPro li queden REMIND_DAYS dies o menys de llicència, se li envia
// un correu (una sola vegada per llicència: `expiryReminderSentAt`). No s'envia si la renovació és automàtica (subscripció
// d'Stripe amb llicència de pagament): Stripe ja avisa de la renovació. Programada cada dia a routes/console.php.
class RemindExpiringLicenses extends Command
{
    public const REMIND_DAYS = 7;

    protected $signature = 'licenses:remind-expiring';

    protected $description = 'Avisa per correu els nutricionistes EvoPro a qui els caduca la llicència en pocs dies';

    public function handle(): int
    {
        $today = Carbon::today();
        $stripe = NutricionistaProfile::whereNotNull('stripeCustomerId')->pluck('userId')->flip();
        $sent = 0;

        User::where('role', 'NUTRICIONISTA')->whereNull('deletedAt')->with('licenses')->each(function (User $nutri) use ($today, $stripe, &$sent) {
            $plan = Licenses::summary($nutri->licenses);
            if ($plan['code'] !== Licenses::PRO || $plan['indefinite'] || ! $plan['until']) {
                return;
            }
            $days = (int) $today->diffInDays(Carbon::parse($plan['until']), false);
            if ($days < 0 || $days > self::REMIND_DAYS) {
                return;
            }
            /** @var NutricionistaLicense|null $license */
            $license = $nutri->licenses->first(fn ($l) => $l->revokedAt === null && $l->endsAt?->toDateString() === $plan['until']);
            if (! $license || $license->expiryReminderSentAt) {
                return;
            }
            if ($stripe->has($nutri->id) && $license->source === 'PAYMENT') {
                return; // renovació automàtica (Stripe)
            }

            try {
                Mail::to($nutri->email)->send(new LicenseExpiringMail(
                    $nutri->name,
                    Carbon::parse($plan['until'])->format('d/m/Y'),
                    $days,
                    rtrim((string) config('app.frontend_url'), '/').'/settings?tab=pla',
                    $nutri->language,
                ));
                $license->forceFill(['expiryReminderSentAt' => now()])->save();
                $sent++;
                $this->line("Avís enviat a {$nutri->email} (caduca el {$plan['until']})");
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $this->info("Recordatoris enviats: $sent");

        return self::SUCCESS;
    }
}

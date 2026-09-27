<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

// Genera una parella de claus VAPID (P-256) per a Web Push i en mostra les línies per al .env. No cal cap llibreria.
// La clau privada és un secret: no es comparteix ni es puja al repositori.
class GeneratePushKeys extends Command
{
    protected $signature = 'push:generate-keys';

    protected $description = 'Genera les claus VAPID per a les notificacions push';

    public function handle(): int
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $key) {
            $this->error('No s\'han pogut generar les claus (cal l\'extensió openssl amb corbes el·líptiques).');

            return self::FAILURE;
        }
        $ec = openssl_pkey_get_details($key)['ec'];
        $b64 = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $pad = fn (string $raw) => str_pad($raw, 32, "\0", STR_PAD_LEFT);

        $public = $b64("\x04".$pad($ec['x']).$pad($ec['y'])); // punt sense comprimir (65 bytes)
        $private = $b64($pad($ec['d']));

        $this->line('Afegeix aquestes línies al .env del backend:');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$public}");
        $this->line("VAPID_PRIVATE_KEY={$private}");
        $this->line('VAPID_SUBJECT=mailto:'.config('mail.from.address'));

        return self::SUCCESS;
    }
}

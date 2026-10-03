<?php

namespace App\Mail;

use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Recordatori al nutricionista: la seva llicència EvoPro caduca aviat (vegeu licenses:remind-expiring).
class LicenseExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $until,
        public int $daysLeft,
        public string $planUrl,
        public ?string $language = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Translator::t('license_expiring_subject', $this->language).' · NutriEvo');
    }

    public function content(): Content
    {
        $lang = $this->language;

        return new Content(
            view: 'emails.license-expiring',
            with: [
                'greeting' => Translator::t('greeting', $lang, ['name' => $this->userName ? ' '.$this->userName : '']),
                // Avui i demà tenen el seu propi text (no «d'aquí 1 dies» ni «d'aquí 0 dies»).
                'body' => Translator::t(
                    $this->daysLeft <= 0 ? 'license_expiring_body_today' : ($this->daysLeft === 1 ? 'license_expiring_body_tomorrow' : 'license_expiring_body'),
                    $lang,
                    ['date' => $this->until, 'days' => $this->daysLeft]
                ),
                'buttonLabel' => Translator::t('license_expiring_button', $lang),
                'actionUrl' => $this->planUrl,
                'footer' => Translator::t('footer_automated', $lang),
                'logoUrl' => Translator::logoUrl($lang),
            ],
        );
    }
}

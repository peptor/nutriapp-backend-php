<?php

namespace App\Mail;

use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Correu de fallback quan una notificació push no arriba enlloc (l'usuari no té les push autoritzades o cap
// dispositiu registrat): mateix contingut genèric que la push (títol + cos, sense dades de salut).
// Vegeu App\Support\PushSender i docs/com-funcionen-les-alertes.md.
class GenericPushMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $pushTitle,
        public string $pushBody,
        public ?string $actionUrl,
        public ?string $language = null,
    ) {}

    public function envelope(): Envelope
    {
        // $pushTitle és sempre "NutriEvo" (nom de la marca, no un títol descriptiu): l'assumpte es basa en el cos.
        return new Envelope(subject: $this->pushBody.' · NutriEvo');
    }

    public function content(): Content
    {
        $lang = $this->language;

        return new Content(
            view: 'emails.generic-push',
            with: [
                'greeting' => Translator::t('greeting', $lang, ['name' => $this->userName ? ' '.$this->userName : '']),
                'buttonLabel' => Translator::t('open_app_button', $lang),
                'disableHint' => Translator::t('disable_hint_push_fallback', $lang),
                'footer' => Translator::t('footer_automated', $lang),
                'pushBody' => $this->pushBody,
                'actionUrl' => $this->actionUrl ?? rtrim(config('app.frontend_url'), '/'),
                'logoUrl' => Translator::logoUrl($lang),
            ],
        );
    }
}

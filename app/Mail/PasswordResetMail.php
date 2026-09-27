<?php

namespace App\Mail;

use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $resetUrl,
        public int $expiresInMinutes,
        public ?string $language = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Translator::t('password_reset_subject', $this->language).' · NutriEvo');
    }

    public function content(): Content
    {
        $lang = $this->language;

        return new Content(
            view: 'emails.password-reset',
            with: [
                'greeting' => Translator::t('greeting', $lang, ['name' => $this->userName ? ' '.$this->userName : '']),
                'intro' => Translator::t('password_reset_intro', $lang),
                'buttonLabel' => Translator::t('password_reset_button', $lang),
                'expiryNote' => Translator::t('password_reset_expiry', $lang, ['minutes' => $this->expiresInMinutes]),
                'ignoreNote' => Translator::t('password_reset_ignore', $lang),
                'fallbackNote' => Translator::t('password_reset_fallback', $lang),
                'footer' => Translator::t('footer_automated', $lang),
                'resetUrl' => $this->resetUrl,
                'logoUrl' => Translator::logoUrl($lang),
            ],
        );
    }
}

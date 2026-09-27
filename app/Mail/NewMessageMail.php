<?php

namespace App\Mail;

use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Avís genèric: no porta ni el títol ni el cos del missatge (són dades de salut).
class NewMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $senderName,
        public string $messageUrl,
        public ?string $language = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Translator::t('new_message_subject', $this->language).' · NutriEvo');
    }

    public function content(): Content
    {
        $lang = $this->language;

        return new Content(
            view: 'emails.new-message',
            with: [
                'greeting' => Translator::t('greeting', $lang, ['name' => $this->userName ? ' '.$this->userName : '']),
                'bodyText' => Translator::t('new_message_body', $lang, ['sender' => $this->senderName]),
                'buttonLabel' => Translator::t('new_message_button', $lang),
                'disableHint' => Translator::t('disable_hint_messages', $lang),
                'footer' => Translator::t('footer_automated', $lang),
                'messageUrl' => $this->messageUrl,
                'logoUrl' => Translator::logoUrl($lang),
            ],
        );
    }
}

<?php

namespace App\Mail;

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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tens un missatge nou · NutriEvo');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-message',
            with: [
                'userName' => $this->userName,
                'senderName' => $this->senderName,
                'messageUrl' => $this->messageUrl,
                'logoUrl' => rtrim(config('app.frontend_url'), '/').'/icon-512.png',
            ],
        );
    }
}

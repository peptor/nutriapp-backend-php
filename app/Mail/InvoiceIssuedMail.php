<?php

namespace App\Mail;

use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Correu al nutricionista quan se li emet la factura d'un pagament de quota EvoPro (App\Support\Invoices).
class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $number,
        public string $concept,
        public string $total,
        public string $invoiceUrl,
        public ?string $language = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Translator::t('invoice_subject', $this->language, ['number' => $this->number]).' · NutriEvo');
    }

    public function content(): Content
    {
        $lang = $this->language;

        return new Content(
            view: 'emails.invoice-issued',
            with: [
                'greeting' => Translator::t('greeting', $lang, ['name' => $this->userName ? ' '.$this->userName : '']),
                'body' => Translator::t('invoice_body', $lang, ['number' => $this->number, 'concept' => $this->concept, 'total' => $this->total]),
                'buttonLabel' => Translator::t('invoice_button', $lang),
                'actionUrl' => $this->invoiceUrl,
                'footer' => Translator::t('footer_automated', $lang),
                'logoUrl' => Translator::logoUrl($lang),
            ],
        );
    }
}

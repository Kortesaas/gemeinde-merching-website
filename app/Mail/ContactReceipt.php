<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactReceipt extends Mailable
{
    /** @param array{contact_name:string,contact_email:string,contact_message:string,contact_phone?:string|null,contact_subject?:string,contact_street?:string|null,contact_postal_code?:string|null,contact_city?:string|null,contact_reply_by?:string,contact_context?:array{path:string,title:string,type:string}|null} $enquiry */
    public function __construct(public readonly string $topic, public readonly array $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Eingangsbestätigung: '.($this->enquiry['contact_subject'] ?? 'Hinweis zur Website'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-receipt');
    }
}

<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessage extends Mailable
{
    /** @param array{contact_name:string,contact_email:string,contact_message:string,contact_phone?:string|null,contact_context?:array{path:string,title:string,type:string}|null} $enquiry */
    public function __construct(public readonly string $topic, public readonly array $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Anfrage über das Kontaktformular', replyTo: [new Address($this->enquiry['contact_email'], $this->enquiry['contact_name'])]);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact');
    }
}

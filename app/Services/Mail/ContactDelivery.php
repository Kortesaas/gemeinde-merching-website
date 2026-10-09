<?php

namespace App\Services\Mail;

use App\Mail\ContactMessage;
use App\Models\ContactRoute;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ContactDelivery
{
    /** @param array{contact_name:string,contact_email:string,contact_message:string,contact_phone?:string|null,contact_context?:array{path:string,title:string,type:string}|null} $data */
    public function send(ContactRoute $route, array $data): void
    {
        $mailer = (string) config('mail.default');
        $transport = config('mail.mailers.'.$mailer.'.transport');
        // A logging/failover transport must never write citizens' messages into application logs.
        if (! in_array($transport, ['smtp', 'sendmail', 'array'], true) || ($transport === 'array' && ! app()->environment('testing'))) {
            throw new RuntimeException('Kontakt-Mailtransport ist nicht eingerichtet.');
        }
        if ($route->recipients === []) {
            throw new RuntimeException('Kontakt-Thema ohne Empfänger.');
        }
        Mail::to($route->recipients)->send(new ContactMessage($route->label, $data));
    }
}

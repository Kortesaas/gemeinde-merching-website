<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\ContactRequest;
use App\Models\ContactRoute;
use App\Services\Audit\AuditLogger;
use App\Services\Mail\ContactDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ContactController
{
    public function create(Request $request): View
    {
        $nonce = Str::random(40);
        $request->session()->put('contact', ['nonce' => $nonce, 'issued_at' => now()->getTimestamp()]);
        $topics = ContactRoute::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($r) => $r->publicData());

        return view('public.contact', ['topics' => $topics, 'nonce' => $nonce]);
    }

    public function store(ContactRequest $request, ContactDelivery $delivery, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $request->session()->forget('contact');
        if (! empty($data['website'])) {
            return redirect()->route('public.contact.success');
        }
        $topic = ContactRoute::query()->where('is_active', true)->findOrFail((int) $data['contact_route_id']);
        try {
            $delivery->send($topic, ['contact_name' => (string) $data['contact_name'], 'contact_email' => (string) $data['contact_email'], 'contact_phone' => isset($data['contact_phone']) ? (string) $data['contact_phone'] : null, 'contact_message' => (string) $data['contact_message']]);
        } catch (Throwable) {
            // Deliberately do not report a transport exception: it may contain mail body or SMTP secrets.
            $audit->record('contact.delivery_failed', null, ['topic_id' => $topic->id]);

            return redirect()->route('public.contact')->withErrors(['general' => 'Die Nachricht konnte derzeit nicht gesendet werden. Bitte versuchen Sie es später erneut.']);
        }
        $audit->record('contact.sent', null, ['topic_id' => $topic->id]);

        return redirect()->route('public.contact.success');
    }

    public function success(): View
    {
        return view('public.contact-success');
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\ContactRequest;
use App\Models\ContactRoute;
use App\Models\SiteSettings;
use App\Services\Audit\AuditLogger;
use App\Services\Content\FeedbackContext;
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
        $request->validate(['feedback' => ['nullable', 'string', 'max:255']]);
        $context = $request->filled('feedback') ? app(FeedbackContext::class)->resolve((string) $request->query('feedback')) : null;
        abort_if($request->filled('feedback') && $context === null, 404);
        $nonce = Str::random(40);
        $request->session()->put('contact', ['nonce' => $nonce, 'issued_at' => now()->getTimestamp(), 'context' => $context]);
        $topics = ContactRoute::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($r) => $r->publicData());

        return view('public.contact', ['topics' => $topics, 'nonce' => $nonce, 'context' => $context, 'selectedTopic' => old('contact_route_id', (string) SiteSettings::query()->find(1)?->central_contact_route_id)]);
    }

    public function store(ContactRequest $request, ContactDelivery $delivery, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $storedContext = $request->session()->get('contact.context');
        $context = is_array($storedContext) && is_string($storedContext['path'] ?? null) ? app(FeedbackContext::class)->resolve($storedContext['path']) : null;
        $request->session()->forget('contact');
        if (! empty($data['website'])) {
            return redirect()->route('public.contact.success');
        }
        $topic = ContactRoute::query()->where('is_active', true)->findOrFail((int) $data['contact_route_id']);
        try {
            $receiptSent = $delivery->send($topic, [
                'contact_name' => (string) $data['contact_name'], 'contact_email' => (string) $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null, 'contact_message' => $data['contact_message'] ?? '',
                'contact_subject' => $data['contact_subject'] ?? 'Hinweis zur Website',
                'contact_street' => $data['contact_street'] ?? null, 'contact_postal_code' => $data['contact_postal_code'] ?? null,
                'contact_city' => $data['contact_city'] ?? null, 'contact_reply_by' => $data['contact_reply_by'] ?? 'email',
                'contact_context' => $context,
            ]);
        } catch (Throwable) {
            // Deliberately do not report a transport exception: it may contain mail body or SMTP secrets.
            $audit->record('contact.delivery_failed', null, ['topic_id' => $topic->id]);

            return redirect()->route('public.contact', $context ? ['feedback' => $context['path']] : [])->withErrors(['general' => 'Die Nachricht konnte derzeit nicht gesendet werden. Bitte versuchen Sie es später erneut.']);
        }
        $audit->record('contact.sent', null, ['topic_id' => $topic->id]);
        if (! $receiptSent) {
            $audit->record('contact.receipt_failed', null, ['topic_id' => $topic->id]);
        }

        return redirect()->route('public.contact.success')->with('contact_receipt_sent', $receiptSent);
    }

    public function success(): View
    {
        return view('public.contact-success');
    }
}

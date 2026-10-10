Thema: {!! $topic !!}
@if (!empty($enquiry['contact_subject']))
Betreff: {!! $enquiry['contact_subject'] !!}
@endif
@if (!empty($enquiry['contact_context']))
Bezug: {!! $enquiry['contact_context']['title'] !!}
Seite: {!! \App\Support\Routing\PublicPath::absoluteUrl($enquiry['contact_context']['path']) !!}
Inhaltstyp: {!! $enquiry['contact_context']['type'] !!}
@endif
Name: {!! $enquiry['contact_name'] !!}
E-Mail: {!! $enquiry['contact_email'] !!}
@if (!empty($enquiry['contact_phone']))
Telefon: {!! $enquiry['contact_phone'] !!}
@endif
@if (!empty($enquiry['contact_street']))
Postanschrift:
{!! $enquiry['contact_street'] !!}
{!! $enquiry['contact_postal_code'] !!} {!! $enquiry['contact_city'] !!}
@endif
Antwort gewünscht: {{ ($enquiry['contact_reply_by'] ?? 'email') === 'post' ? 'Auf dem Postweg' : 'Mittels unverschlüsselter E-Mail' }}

{!! $enquiry['contact_message'] !!}

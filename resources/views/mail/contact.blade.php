Thema: {!! $topic !!}
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

{!! $enquiry['contact_message'] !!}

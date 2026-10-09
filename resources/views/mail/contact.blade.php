Thema: {!! $topic !!}
Name: {!! $enquiry['contact_name'] !!}
E-Mail: {!! $enquiry['contact_email'] !!}
@if (!empty($enquiry['contact_phone']))
Telefon: {!! $enquiry['contact_phone'] !!}
@endif

{!! $enquiry['contact_message'] !!}

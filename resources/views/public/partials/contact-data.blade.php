@if ($contact->phone)<p><a href="tel:{{ preg_replace('/[^+0-9]/', '', $contact->phone) }}">{{ $contact->phone }}</a></p>@endif
@if ($contact->email)<p><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></p>@endif

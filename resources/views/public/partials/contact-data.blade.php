@if ($contact->phone || $contact->email)
<ul class="contact-data">
    @if ($contact->phone)@foreach (\App\Support\Content\PublicFormat::phoneLinks($contact->phone) as $phone)<li><x-icon name="phone" /><a href="{{ $phone['href'] }}"><span class="visually-hidden">Telefon: </span>{{ $phone['label'] }}</a></li>@endforeach @endif
    @if ($contact->email)<li><x-icon name="mail" /><a href="mailto:{{ $contact->email }}"><span class="visually-hidden">E-Mail: </span>{{ $contact->email }}</a></li>@endif
</ul>
@endif

@if ($contact->phone || $contact->email)
<ul class="contact-data">
    @if ($contact->phone)<li><x-icon name="phone" /><a href="{{ \App\Support\Content\PublicFormat::phoneHref($contact->phone) }}"><span class="visually-hidden">Telefon: </span>{{ $contact->phone }}</a></li>@endif
    @if ($contact->email)<li><x-icon name="mail" /><a href="mailto:{{ $contact->email }}"><span class="visually-hidden">E-Mail: </span>{{ $contact->email }}</a></li>@endif
</ul>
@endif

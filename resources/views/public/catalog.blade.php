@extends('layouts.public')
@section('title', $section['title'])
@section('content')
<h1>{{ ($model ?? null)?->displayTitle() ?? $section['title'] }}</h1>
@isset($model)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->body) !!}@include('public.partials.blocks')</div>@endisset
@if ($kind === 'services')<p class="lead">Leistungen finden, Unterlagen vorbereiten und die zuständige Stelle erreichen.</p>@include('public.partials.search-form', ['searchId'=>'service-search', 'searchLabel'=>'Im Bürgerservice suchen', 'searchType'=>'service'])<a class="button" href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('az') }}">Leistungen von A bis Z</a>@endif
<form method="GET" class="filter-form" role="search" aria-label="Einträge filtern">
    <x-form.field name="q" label="Anliegen oder Titel" type="search" :value="$term" maxlength="150" />
    @if ($categories !== [])<x-form.select name="category" label="Kategorie" :options="$categories" :value="request('category')" placeholder="Alle Kategorien" />@endif
    @if (in_array($kind, ['services','az']))<label><input name="online" type="checkbox" value="1" @checked(request()->boolean('online'))> Mit Online-Dienst</label>@endif
    @if (in_array($kind, ['articles','events','notices','documents']))<label><input name="archiv" type="checkbox" value="1" @checked(request()->boolean('archiv'))> Öffentliches Archiv</label>@endif
    <button class="button button--secondary" type="submit">Filtern</button>
</form>
@if ($kind === 'az')
    <nav class="alphabet" aria-label="Anfangsbuchstaben">@foreach ($groups->keys()->sort() as $letter)<a href="#letter-{{ $loop->index }}">{{ $letter }}</a>@endforeach</nav>
    @foreach ($groups->sortKeys() as $letter => $group)<section class="alphabet-section" id="letter-{{ $loop->index }}"><h2>{{ $letter }}</h2><ul class="link-rows">@foreach ($group as $record)<li>@include('public.partials.card', ['headingTag'=>'h3'])</li>@endforeach</ul></section>@endforeach
    @if ($groups->isEmpty())<p class="empty-state">Keine Leistungen gefunden.</p>@endif
@else
    @if ($records->isEmpty())<p class="empty-state">Hier sind derzeit keine Einträge verfügbar.</p>
    @else<ul class="link-rows">@foreach ($records as $record)<li>@include('public.partials.card')</li>@endforeach</ul>{{ $records->links('admin.partials.pagination') }}@endif
@endif
@if ($situations->isNotEmpty())<section class="section"><h2>Lebenslagen</h2><ul class="link-rows task-grid">@foreach ($situations as $situation)<li><a href="{{ \App\Support\Routing\PublicPath::toUrl($situation->publicPath()) }}">{{ $situation->title }}</a></li>@endforeach</ul></section>@endif
@endsection

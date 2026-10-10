{{-- Native GET filters; work without JavaScript. --}}
<form method="GET" class="filter-bar" role="search" aria-label="{{ $filterLabel ?? 'Einträge filtern' }}">
    @if ($kind === 'events')<input type="hidden" name="monat" value="{{ $calendar['month']->format('Y-m') }}">@endif
    @if ($withSearch ?? true)
        <div class="filter-bar__search">
            @include('public.partials.search-form-inner', ['inputId' => 'catalog-q', 'inputLabel' => $searchLabel ?? 'Titel oder Stichwort'])
        </div>
    @endif
    @if ($categories !== [] || in_array($kind, ['services', 'az', 'articles', 'events', 'notices', 'documents'], true))
        <div class="filter-bar__options">
            @if ($categories !== [])
                <div class="filter-bar__select"><label for="category">Thema</label>
                    <select class="form-input" id="category" name="category"><option value="">Alle Themen</option>@foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((string) request('category') === (string) $id)>{{ $name }}</option>@endforeach</select>
                </div>
            @endif
            @if (in_array($kind, ['services', 'az'], true))<label class="filter-bar__toggle"><input name="online" type="checkbox" value="1" @checked(request()->boolean('online'))> Nur mit Online-Dienst</label>@endif
            @if (in_array($kind, ['articles', 'notices', 'documents'], true))<label class="filter-bar__toggle"><input name="archiv" type="checkbox" value="1" @checked(request()->boolean('archiv'))> Archiv anzeigen</label>@endif
            @unless ($withSearch ?? true)<button class="button button--secondary" type="submit">Anwenden</button>@endunless
            @if ($filtered || request()->boolean('archiv'))<a class="filter-bar__reset" href="{{ $kind === 'events' ? request()->fullUrlWithQuery(['q' => null, 'category' => null, 'online' => null, 'archiv' => null, 'page' => null]) : url()->current() }}">Filter zurücksetzen</a>@endif
        </div>
    @endif
</form>

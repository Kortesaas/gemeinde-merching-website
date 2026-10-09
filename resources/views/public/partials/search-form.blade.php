<form method="GET" action="{{ route('public.search') }}" role="search" class="search-form {{ $hero ?? false ? 'search-form--hero' : '' }}" data-search-form>
    <div class="form-field">
        <label class="form-label" for="{{ $searchId }}">{{ $searchLabel ?? 'Website durchsuchen' }}</label>
        <input class="form-input" id="{{ $searchId }}" name="q" type="search" value="{{ $searchValue ?? '' }}" maxlength="150" autocomplete="off" data-search-input>
    </div>
    @isset($searchType)<input type="hidden" name="type" value="{{ $searchType }}">@endisset
    <button class="button" type="submit">Suchen</button>
    <ul class="suggestions" id="{{ $searchId }}-suggestions" data-suggestions hidden></ul>
    <p class="visually-hidden" role="status" data-search-status></p>
</form>

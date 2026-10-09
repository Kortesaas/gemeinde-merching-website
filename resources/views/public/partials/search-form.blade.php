@php
    $suggest ??= true;
    $pill ??= false;
    $labelHidden ??= $pill && ! ($labelVisible ?? false);
@endphp
<form method="GET" action="{{ route('public.search') }}" role="search" class="search-form {{ $pill ? 'search-form--pill' : '' }}" @if ($suggest) data-search-form @endif>
    <label class="search-form__label {{ $labelHidden ? 'visually-hidden' : '' }}" for="{{ $searchId }}">{{ $searchLabel ?? 'Website durchsuchen' }}</label>
    <div class="search-form__field">
        <x-icon name="search" class="search-form__icon" />
        <input class="search-form__input" id="{{ $searchId }}" name="q" type="search" value="{{ $searchValue ?? '' }}" maxlength="150" autocomplete="off" enterkeyhint="search" @if ($suggest) data-search-input @endif>
        <button class="button search-form__submit" type="submit">{{ $submitLabel ?? 'Suchen' }}</button>
    </div>
    @isset($searchType)<input type="hidden" name="type" value="{{ $searchType }}">@endisset
    @if ($suggest)
        <ul class="suggestions" id="{{ $searchId }}-suggestions" data-suggestions hidden></ul>
        <p class="visually-hidden" role="status" data-search-status></p>
    @endif
</form>

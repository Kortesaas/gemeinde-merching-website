<label class="visually-hidden" for="{{ $inputId }}">{{ $inputLabel }}</label>
<div class="search-form__field search-form__field--pill">
    <x-icon name="search" class="search-form__icon" />
    <input class="search-form__input" id="{{ $inputId }}" name="q" type="search" value="{{ request('q') }}" maxlength="150" autocomplete="off">
    <button class="button search-form__submit" type="submit">{{ $submitLabel ?? 'Filtern' }}</button>
</div>

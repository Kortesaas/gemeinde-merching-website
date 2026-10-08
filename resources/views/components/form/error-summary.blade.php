{{--
    Error summary at the top of a form page. It receives focus on page load
    (autofocus, no JavaScript needed) so screen-reader users hear the errors;
    each entry links to its field.
--}}
@if (isset($errors) && $errors->any())
    <div class="error-summary" tabindex="-1" autofocus aria-labelledby="error-summary-title">
        <h2 id="error-summary-title" class="error-summary__title">Bitte prüfen Sie Ihre Angaben</h2>
        <ul class="error-summary__list">
            @foreach ($errors->messages() as $field => $messages)
                <li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif

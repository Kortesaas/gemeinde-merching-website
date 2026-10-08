{{-- One-time status message from the session (backend only – the public site has no session). --}}
@if (session('status'))
    <div class="notice" role="status">
        <p>{{ session('status') }}</p>
    </div>
@endif

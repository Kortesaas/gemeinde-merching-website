<footer class="content-footer">
    <p>Stand: {{ \App\Support\SiteTime::format($model->updated_at, 'd.m.Y') }}</p>
    @if ($model->publicPath())<a href="{{ route('public.contact', ['feedback' => $model->publicPath()]) }}">Fehler auf dieser Seite melden</a>@endif
</footer>

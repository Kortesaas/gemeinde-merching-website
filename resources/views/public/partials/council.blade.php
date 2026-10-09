@if ($model->is_historical)<p>Historische Wahlperiode</p>@endif
@if ($model->starts_on || $model->ends_on)<p>Wahlperiode: {{ \App\Support\SiteTime::format($model->starts_on, 'd.m.Y') }}@if ($model->ends_on) bis {{ \App\Support\SiteTime::format($model->ends_on, 'd.m.Y') }}@endif</p>@endif
<h2>Gemeinderat</h2>
<ul>
    @foreach ($model->memberships()->with('member')->get() as $membership)
        @if ($membership->member && $membership->member->isPubliclyReachable())
            <li>{{ $membership->member->title }} – {{ $membership->role }}@if ($membership->grouping) ({{ $membership->grouping }})@endif</li>
        @endif
    @endforeach
</ul>
@foreach ($model->committees()->get() as $committee)
    @if ($committee->isPubliclyReachable())
        <h2>{{ $committee->title }}</h2>@if ($committee->description)<p>{{ $committee->description }}</p>@endif
        <ul>
            @foreach ($committee->committeeMemberships()->with('member')->get() as $membership)
                @if ($membership->member && $membership->member->isPubliclyReachable() && $model->memberships()->where('council_member_id', $membership->council_member_id)->exists())
                    <li>{{ $membership->member->title }} – {{ $membership->role }}</li>
                @endif
            @endforeach
        </ul>
    @endif
@endforeach

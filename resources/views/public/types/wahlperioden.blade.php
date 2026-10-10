@php
    $memberships = $model->memberships()->with('member.portrait')->get()->filter(fn ($m) => $m->member && $m->member->isPubliclyReachable());
    $grouped = $memberships->groupBy(fn ($m) => $m->grouping ?: 'Ohne Gruppierung');
    $memberIds = $memberships->pluck('council_member_id')->all();
    $committees = $model->committees()->get()->filter(fn ($c) => $c->isPubliclyReachable());
@endphp
@include('public.partials.page-header', ['eyebrow' => $model->is_historical ? 'Gemeinderat · Historische Wahlperiode' : 'Gemeinderat', 'lead' => $model->description])
@if ($model->starts_on || $model->ends_on)
    <dl class="meta-list">
        <div><dt>Wahlperiode</dt><dd>{{ \App\Support\SiteTime::format($model->starts_on, 'd.m.Y') }}@if ($model->ends_on) bis {{ \App\Support\SiteTime::format($model->ends_on, 'd.m.Y') }}@endif</dd></div>
        <div><dt>Mitglieder</dt><dd>{{ $memberships->count() }}</dd></div>
    </dl>
@endif
<div class="content-layout content-layout--wide">
    <div class="content-main">
        <section class="content-section" aria-labelledby="council-members"><h2 id="council-members">Mitglieder</h2>
            @foreach ($grouped as $group => $members)
                <h3 class="group-label">{{ $group }} <span class="meta">({{ $members->count() }})</span></h3>
                <ul class="member-grid">
                    @foreach ($members as $membership)
                        <li class="member-card"><div class="member-card__portrait" aria-hidden="true">
                            @php $portrait = $membership->member->portrait; @endphp
                            @if ($portrait && $portrait->isImage() && $portrait->isPubliclyReachable())
                                @include('public.partials.image', ['medium' => $portrait, 'imageAlt' => '', 'imageSizes' => '(max-width: 40rem) 40vw, 15rem'])
                            @else
                                <img src="{{ asset('images/council-placeholder.svg') }}" alt="" width="400" height="500" loading="lazy">
                            @endif
                        </div><span class="member-card__name">{{ $membership->member->title }}</span><span class="member-card__role">{{ $membership->role }}</span></li>
                    @endforeach
                </ul>
            @endforeach
        </section>
        @if ($committees->isNotEmpty())
            <section class="content-section" aria-labelledby="committees"><h2 id="committees">Ausschüsse</h2>
                @foreach ($committees as $committee)
                    @php $rows = $committee->committeeMemberships()->with('member.portrait')->get()->filter(fn ($m) => $m->member && $m->member->isPubliclyReachable() && in_array($m->council_member_id, $memberIds, true)); @endphp
                    <details class="accordion">
                        <summary><span>{{ $committee->title }} @if ($rows->isNotEmpty())<span class="meta">· {{ $rows->count() }} Mitglieder</span>@endif</span><x-icon name="plus" class="accordion__plus" /><x-icon name="minus" class="accordion__minus" /></summary>
                        <div class="accordion__body">
                            @if ($committee->description)<p>{{ $committee->description }}</p>@endif
                            @if ($rows->isEmpty())<p>Mitglieder sind noch nicht veröffentlicht.</p>@else<ul class="plain-list committee-list">@foreach ($rows as $row)<li><span>{{ $row->member->title }}</span> <span class="meta">{{ $row->role }}</span></li>@endforeach</ul>@endif
                        </div>
                    </details>
                @endforeach
            </section>
        @endif
    </div>
</div>

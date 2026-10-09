@extends('layouts.public')
@php
    $storage = app(\App\Services\Content\DocumentStorage::class);
    $documents = method_exists($model, 'documents')
        ? $model->documents()->with(['canonicalRoute', 'replacedBy.canonicalRoute', 'accessibleAlternative.canonicalRoute'])->get()->filter(fn ($d) => $d->isPubliclyReachable() && $storage->exists($d))->values()
        : collect();
    $links = method_exists($model, 'externalResources') ? $model->externalResources()->visible()->get() : collect();
    $contacts = method_exists($model, 'contacts') ? $model->contacts()->where('is_active', true)->get() : collect();
    $responsible = method_exists($model, 'department') && $model->department?->isPubliclyReachable() ? $model->department : null;
    $type = $model->getMorphClass();
    $view = view()->exists('public.types.'.$type) ? 'public.types.'.$type : 'public.types.page';
@endphp
@section('title', $model->displayTitle())
@section('canonical', \App\Support\Content\SeoUrl::path((string) $model->publicPath()))
@section('content')
<article class="content content--{{ $type }}">
    @include($view)
    @include('public.partials.feedback')
</article>
@endsection

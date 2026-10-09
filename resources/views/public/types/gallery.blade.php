@include('public.partials.page-header', ['eyebrow' => 'Bildergalerie', 'lead' => $model->description])
@include('public.partials.gallery', ['gallery' => $model, 'showTitle' => false])

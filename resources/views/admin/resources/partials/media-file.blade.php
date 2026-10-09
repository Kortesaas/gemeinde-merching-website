@if ($model->exists)
    <p>Datei: {{ $model->original_filename }} ({{ $model->mime_type }}, {{ $model->size_bytes }} Bytes)
       – <a href="{{ route('admin.media.file',$model->getKey()) }}">Datei ansehen</a></p>
    @if ($model->width)<p>Bildgröße: {{ $model->width }} × {{ $model->height }} Pixel</p>@endif
@endif
<x-form.field name="file" type="file" :label="$model->exists ? 'Datei ersetzen (optional)' : 'Datei'" :required="!$model->exists" :disabled="$disabled" hint="Nur erlaubte Dateitypen; Bilder werden geprüft und ohne EXIF-Metadaten gespeichert." />

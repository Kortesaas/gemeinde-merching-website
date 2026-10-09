@if ($model->exists)
    @if ($model->isImage())<img class="media-file-preview" src="{{ route('admin.media.file', ['record' => $model->getKey(), 'width' => 480]) }}" alt="Bildvorschau: {{ $model->title }}" width="{{ $model->width }}" height="{{ $model->height }}">@endif
    <p>Datei: {{ $model->original_filename }} ({{ strtoupper($model->extension ?? pathinfo($model->original_filename, PATHINFO_EXTENSION)) }}, {{ \App\Support\Content\PublicFormat::fileSize($model->size_bytes) }})
       – <a href="{{ route('admin.media.file',$model->getKey()) }}">Datei ansehen</a></p>
    @if ($model->width)<p>Bildgröße: {{ $model->width }} × {{ $model->height }} Pixel</p>@endif
@endif
<x-form.field name="file" type="file" :label="$model->exists ? 'Datei ersetzen (optional)' : 'Datei (Pflichtfeld)'" :required="!$model->exists" :disabled="$disabled" accept="{{ collect(array_keys(config('uploads.types')))->map(fn ($extension) => '.'.$extension)->implode(',') }}"
    hint="Erlaubt: {{ strtoupper(implode(', ', array_keys(config('uploads.types')))) }}; max. {{ (int) (config('uploads.max_kilobytes') / 1024) }} MB. Bilder werden beim Hochladen geprüft." />

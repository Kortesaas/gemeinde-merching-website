{{-- Quality severity: icon + word, never colour alone. --}}
@php $icon = match ($severity) { \App\Enums\QualitySeverity::Error => 'error', \App\Enums\QualitySeverity::Warning => 'warning', default => 'info' }; @endphp
<span class="severity severity--{{ $severity->value }}"><x-icon :name="$icon" />{{ $severity->label() }}</span>

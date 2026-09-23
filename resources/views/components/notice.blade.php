@props(['type' => 'info'])
@php
    $styles = [
        'info' => 'border-sky-200 bg-sky-50 text-sky-900',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        'warn' => 'border-amber-300 bg-amber-50 text-amber-900',
    ][$type] ?? 'border-sky-200 bg-sky-50 text-sky-900';
@endphp
<div {{ $attributes->merge(['class' => "mb-4 rounded-lg border px-4 py-3 text-sm $styles"]) }} role="{{ $type === 'error' ? 'alert' : 'status' }}">{{ $slot }}</div>

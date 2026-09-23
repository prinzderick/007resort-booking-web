@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'hint' => null])
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-800">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif required
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
           class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/30">
    @if ($hint)<p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
</div>

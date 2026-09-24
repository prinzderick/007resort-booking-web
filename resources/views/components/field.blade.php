@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'hint' => null, 'required' => true, 'rows' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows ?? 5 }}" @if ($required) required @endif @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($required) required @endif
               @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @endif
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="err">{{ $message }}</p>@enderror
</div>

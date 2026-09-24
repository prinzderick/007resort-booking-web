@props(['type' => 'info'])
<div {{ $attributes->class(['notice', 'notice--'.$type]) }} role="{{ $type === 'error' ? 'alert' : 'status' }}"><div>{{ $slot }}</div></div>

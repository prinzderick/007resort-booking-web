@extends('layouts.app')
@section('title', $provider.' (demo)')
@section('noindex', '1')
@section('content')
<div class="page-pad"><div class="wrap"><div class="auth">
    <span class="eyebrow">Development only</span>
    <h1>Fake <i>{{ $provider }}.</i></h1>
    <x-notice type="warn">This is a local stand-in for the {{ $provider }} consent screen (<code>SOCIAL_FAKE=true</code>). It does not exist in production. Pick who you are pretending to be.</x-notice>
    <ul class="stack plain" role="list">
        @foreach ($personas as $code => [$label, $desc])
            <li>
                <a class="row-item" href="{{ url('/auth/'.$key.'/callback').'?'.http_build_query(['code' => $code, 'state' => $state]) }}">
                    <div><p style="font-weight:600">{{ $label }}</p><p style="font-size:14px;color:var(--mute)">{{ $desc }}</p></div>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </li>
        @endforeach
        <li><a class="row-item" href="{{ url('/auth/'.$key.'/callback').'?'.http_build_query(['error' => 'access_denied', 'error_description' => 'The user denied access', 'state' => $state]) }}"><div><p style="font-weight:600">Press Cancel</p><p style="font-size:14px;color:var(--mute)">Returns error=access_denied like the real provider.</p></div><span aria-hidden="true">&rarr;</span></a></li>
        <li><a class="row-item" href="{{ url('/auth/'.$key.'/callback').'?'.http_build_query(['code' => 'new', 'state' => 'tampered']) }}"><div><p style="font-weight:600">Tampered state</p><p style="font-size:14px;color:var(--mute)">Callback with a wrong state value (must be refused).</p></div><span aria-hidden="true">&rarr;</span></a></li>
    </ul>
</div></div></div>
@endsection

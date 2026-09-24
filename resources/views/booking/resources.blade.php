@use('App\Support\Money')
@extends('layouts.app')
@section('title', 'Book '.$facility['name'])
@section('description', 'Choose a '.($facility['noun'] ?? 'service').' at '.$facility['name'].' and reserve a time online.')
@section('no_cta', '1')

@section('content')
<div class="wrap">
    <div class="book-head">
        <nav class="crumbs" aria-label="Breadcrumb" style="color:var(--mute)"><a href="{{ route('facility', $facility['slug']) }}">{{ $facility['name'] }}</a><span aria-hidden="true">/</span><span aria-current="page">Book</span></nav>
        <h1>Book: {{ $facility['name'] }}</h1>
        <p class="lede" style="margin-top:10px">Choose a {{ $facility['noun'] ?? 'service' }}, then pick a date and time.</p>
    </div>

    @if ($notice)
        <x-notice type="warn">{{ $notice }} <a href="{{ route('contact') }}">Contact us</a></x-notice>
    @endif

    <ul class="grid plain" role="list" style="padding-block:24px clamp(56px,8vw,110px)">
        @foreach ($resources as $r)
            @php $paused = ($r['onlineAvailable'] ?? true) === false; @endphp
            <li class="card reveal" style="--i:{{ $loop->index % 6 }}">
                <div class="card-body" style="padding:26px">
                    <h2 class="h-3">{{ $r['name'] }}</h2>
                    <p class="meta"><b style="color:var(--ink)">{{ Money::format($r['price']) }}</b><span>&middot; {{ $r['slotMinutes'] }} min</span></p>
                    @if ($paused)
                        <p>{{ $r['onlineNotice'] ?? 'Online booking paused for this item.' }}</p>
                    @else
                        <p><a href="{{ route('book.slots', array_filter(['slug' => $facility['slug'], 'resourceId' => $r['id'], 'date' => request('date'), 'players' => request('players')])) }}" class="btn btn--sm">See times <span class="arr" aria-hidden="true">&rarr;</span></a></p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    @if (! $notice && count($resources) === 0)
        <p class="empty">Nothing is bookable online right now. Please contact reception.</p>
    @endif
</div>
@endsection

@use('App\Support\Text')
@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('hero', '1')

@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Events', 'item' => url()->current()]]]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'title' => $page['title'], 'sub' => $page['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ["What's on"]]])
    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section" style="padding-top:clamp(40px,6vw,80px)">
            <div class="wrap">
                @if ($featured && $month === '' && $cat === '')
                    <a class="feature-post reveal" href="{{ route('events.show', $featured['slug']) }}{{ ! empty($featured['isRecurring']) ? '?start='.urlencode($featured['startsAt']) : '' }}" style="margin-bottom:44px">
                        <div class="ph zoom"><x-img :m="$featured['cover']" sizes="(min-width: 800px) 60vw, 100vw" alt="" /></div>
                        <div class="b">
                            <span class="badge badge--sun" style="width:max-content">Featured</span>
                            <h2>{{ $featured['title'] }}</h2>
                            <p class="lede">{{ $featured['summary'] }}</p>
                            <p class="meta tnum" style="color:var(--mute)">{{ Lagos::parse($featured['startsAt'])->format('l j F, g:ia') }} · {{ $featured['venue']['label'] ?? '' }}</p>
                        </div>
                    </a>
                @endif

                <form class="stack" method="GET" action="{{ route('events.index') }}" data-filter-form aria-label="Filter events">
                    <div class="chips scroll" role="group" aria-label="Month">
                        <a class="chip" href="{{ route('events.index', array_filter(['category' => $cat])) }}" @if ($month === '') aria-current="true" @endif>All dates</a>
                        @foreach ($months as $ym => $label)
                            <a class="chip" href="{{ route('events.index', array_filter(['month' => $ym, 'category' => $cat])) }}" @if ($month === $ym) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </div>
                    <div class="chips scroll" role="group" aria-label="Type">
                        <a class="chip" href="{{ route('events.index', array_filter(['month' => $month])) }}" @if ($cat === '') aria-current="true" @endif>Everything</a>
                        @foreach ($cats as $c)
                            <a class="chip" href="{{ route('events.index', array_filter(['month' => $month, 'category' => $c])) }}" @if ($cat === $c) aria-current="true" @endif>{{ ucfirst(strtolower($c)) }}</a>
                        @endforeach
                    </div>
                </form>

                <div aria-live="polite">
                    @forelse ($grouped as $label => $items)
                        <h2 class="month-head">{{ $label }}</h2>
                        @foreach ($items as $e)
                            @php $d = Lagos::parse($e['startsAt']); @endphp
                            <a class="ev-row reveal" href="{{ route('events.show', $e['slug']) }}{{ ! empty($e['isRecurring']) ? '?start='.urlencode($e['startsAt']) : '' }}">
                                <div class="dt"><b>{{ $d->format('j') }}</b><span>{{ $d->format('D') }}</span></div>
                                <div>
                                    <h3>{{ $e['title'] }}</h3>
                                    <p class="tnum">{{ $d->format('g:ia') }} · {{ $e['venue']['label'] ?? '' }}@if (! empty($e['priceText'])) · {{ $e['priceText'] }}@endif</p>
                                    <p style="margin-top:6px"><span class="badge" style="background:var(--paper-2)">{{ ucfirst(strtolower($e['category'])) }}</span>@if (! empty($e['isRecurring'])) <span class="badge" style="background:var(--paper-2)">Weekly</span>@endif</p>
                                </div>
                                <div class="ph zoom"><x-img :m="$e['cover'] ?? null" sizes="150px" alt="" /></div>
                            </a>
                        @endforeach
                    @empty
                        <div class="empty"><h3>Nothing here yet</h3><p>No events match that filter. <a href="{{ route('events.index') }}" style="font-weight:600">See everything coming up</a>.</p></div>
                    @endforelse
                </div>
            </div>
        </section>
        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="event" variant="light" title="Never miss match night." text="New events and offers, one email a week." /></div></section>
    </div>
@endsection

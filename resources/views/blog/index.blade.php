@use('App\Support\Text')
@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('hero', '1')

@php
    $base = fn (array $o = []) => route('blog.index', array_filter(array_merge(['category' => $cat, 'q' => $q], $o)));
@endphp

@section('content')
    @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'eyebrow' => 'The journal', 'title' => $page['title'], 'sub' => $page['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ['Journal']]])
    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section" style="padding-top:clamp(40px,6vw,80px)">
            <div class="wrap">
                @if ($featured)
                    <a class="feature-post reveal" href="{{ route('blog.show', $featured['slug']) }}" style="margin-bottom:44px">
                        <div class="ph zoom"><x-img :m="$featured['cover'] ?? null" sizes="(min-width: 800px) 60vw, 100vw" eager alt="" /></div>
                        <div class="b">
                            <span class="badge badge--sun" style="width:max-content">Featured @if (! empty($featured['category']['name']))· {{ $featured['category']['name'] }}@endif</span>
                            <h2>{{ $featured['title'] }}</h2>
                            <p class="lede">{{ $featured['excerpt'] }}</p>
                            <p class="meta" style="color:var(--mute)">{{ Lagos::parse($featured['publishedAt'])->format('j F Y') }} · {{ $featured['readingTimeMinutes'] ?? 3 }} min read @if (! empty($featured['authorName']))· {{ $featured['authorName'] }}@endif</p>
                        </div>
                    </a>
                @endif

                <div style="display:flex;gap:18px;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-bottom:30px">
                    <div class="chips scroll" role="group" aria-label="Categories">
                        <a class="chip" href="{{ $base(['category' => null, 'page' => null]) }}" @if ($cat === '') aria-current="true" @endif>All</a>
                        @foreach ($categories as $c)<a class="chip" href="{{ $base(['category' => $c['slug'], 'page' => null]) }}" @if ($cat === $c['slug']) aria-current="true" @endif>{{ $c['name'] }} <small>{{ $c['postCount'] ?? '' }}</small></a>@endforeach
                    </div>
                    <form class="searchbar" method="GET" action="{{ route('blog.index') }}" role="search">
                        @if ($cat)<input type="hidden" name="category" value="{{ $cat }}">@endif
                        <label class="sr-only" for="blog-q">Search stories</label>
                        <input id="blog-q" type="search" name="q" value="{{ $q }}" placeholder="Search stories">
                        <button class="btn btn--sm" type="submit">Search</button>
                    </form>
                </div>

                @if ($q !== '')<p style="margin-bottom:20px;color:var(--mute)" role="status">{{ $total }} {{ \Illuminate\Support\Str::plural('result', $total) }} for "{{ $q }}". <a href="{{ $base(['q' => null]) }}" style="font-weight:600">Clear search</a></p>@endif

                @if (count($posts))
                    <div class="grid">@foreach ($posts as $p)<div class="reveal" style="--i:{{ $loop->index % 3 }};display:flex">@include('partials.card-post', ['p' => $p])</div>@endforeach</div>
                @else
                    <div class="empty"><h3>No stories found</h3><p>Try a different word or category.</p></div>
                @endif

                @if ($pages > 1)
                    <nav class="pager" aria-label="Pages">
                        @if ($current > 1)<a href="{{ $base(['page' => $current - 1]) }}" rel="prev">Previous</a>@endif
                        @foreach (range(1, $pages) as $n)@if ($n === $current)<span aria-current="page">{{ $n }}</span>@else<a href="{{ $base(['page' => $n]) }}">{{ $n }}</a>@endif @endforeach
                        @if ($current < $pages)<a href="{{ $base(['page' => $current + 1]) }}" rel="next">Next</a>@endif
                    </nav>
                @endif
            </div>
        </section>
        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="blog" variant="light" /></div></section>
    </div>
@endsection

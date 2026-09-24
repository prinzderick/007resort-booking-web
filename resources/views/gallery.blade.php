@use('App\Support\Text')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('hero', '1')

@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'ImageGallery', 'name' => Text::plain($page['title']), 'url' => url()->current()]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'eyebrow' => 'Gallery', 'title' => $page['title'], 'sub' => $page['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ['Gallery']]])
    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section" style="padding-top:clamp(40px,6vw,80px)">
            <div class="wrap" data-gallery>
                <div class="chips scroll" role="group" aria-label="Albums" style="margin-bottom:30px">
                    <a class="chip" href="{{ route('gallery') }}" data-album="" @if ($active === '') aria-current="true" @endif>All <small>{{ count($photos) }}</small></a>
                    @foreach ($albums as $a)<a class="chip" href="{{ route('gallery', ['album' => $a['slug']]) }}" data-album="{{ $a['slug'] }}" @if ($active === $a['slug']) aria-current="true" @endif>{{ $a['title'] }} <small>{{ $a['itemCount'] }}</small></a>@endforeach
                </div>
                @if (! count($photos))<div class="empty"><h3>Photos are on their way</h3><p>Check back soon.</p></div>@endif
                <div class="masonry" data-masonry>
                    @foreach ($photos as $p)
                        @php $m = $p['media']; $big = collect($m['variants'] ?? [])->sortBy('width')->last()['url'] ?? $m['url']; $cap = $p['caption'] ?: ($p['alt'] ?: ($m['alt'] ?? '')); @endphp
                        <figure data-album="{{ $p['album'] }}" class="{{ $active !== '' && $active !== $p['album'] ? 'is-hidden' : '' }}">
                            <a href="{{ $big }}" data-photo="{{ $p['id'] }}" data-caption="{{ $cap }}" data-credit="{{ $m['credit'] ?? '' }}" data-full="{{ $big }}" aria-label="Open photo: {{ \Illuminate\Support\Str::limit($cap, 80) }}">
                                <x-img :m="$m" :alt="$p['alt'] ?: ($m['alt'] ?? '')" sizes="(min-width: 1000px) 30vw, (min-width: 600px) 45vw, 100vw" />
                                <figcaption>{{ \Illuminate\Support\Str::limit($cap, 90) }}</figcaption>
                            </a>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="blog" variant="light" /></div></section>
    </div>
@endsection
@push('modals')
    <div class="lb" data-lightbox role="dialog" aria-modal="true" aria-label="Photo viewer" aria-hidden="true">
        <div class="lb-top"><span data-lb-count class="tnum"></span><button type="button" data-lb-close aria-label="Close viewer">&times;</button></div>
        <div class="lb-stage"><button class="prev" type="button" data-lb-prev aria-label="Previous photo">&lsaquo;</button><img alt="" data-lb-img><button class="next" type="button" data-lb-next aria-label="Next photo">&rsaquo;</button></div>
        <div class="lb-cap" data-lb-cap aria-live="polite"></div>
    </div>
@endpush

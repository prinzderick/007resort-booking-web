@use('App\Support\Text')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('og_image', $page['seo']['ogImage']['url'] ?? ($page['hero']['url'] ?? ''))
@if (! ($legal || (! $page['hero'] && $slug !== 'faq')))@section('hero', '1')@endif

@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => Text::plain($page['title']), 'item' => url()->current()]]]" />
    @if ($faqs)
        <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faqs)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->all()]" />
    @endif
@endpush

@section('content')
    @if ($legal || (! $page['hero'] && $slug !== 'faq'))
        <section class="plain-hero"><div class="wrap narrow"><h1 class="h-1">{{ Text::accent($page['title'], false) }}</h1>@if ($page['subtitle'])<p class="lede" style="margin-top:14px">{{ $page['subtitle'] }}</p>@endif</div></section>
    @else
        @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'eyebrow' => null, 'title' => $page['title'], 'sub' => $page['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], [Text::plain($page['title'])]]])
    @endif

    <div class="slide-over" style="margin-top:0;border-radius:0">
        @if (trim($page['bodyHtml']) !== '')
            <section class="section"><div class="wrap"><div class="narrow prose reveal">{!! $page['bodyHtml'] !!}</div></div></section>
        @endif

        @if ($faqs)
            @php $groups = collect($faqs)->groupBy(fn ($f) => $f['topic'] ?: 'General'); @endphp
            <section class="section" style="padding-top:clamp(40px,6vw,80px)">
                <div class="wrap narrow">
                    @if ($groups->count() > 1)
                        <div class="chips scroll" role="group" aria-label="Jump to topic" style="margin-bottom:30px">
                            @foreach ($groups as $topic => $items)<a class="chip" href="#topic-{{ \Illuminate\Support\Str::slug($topic) }}">{{ $topic }} <small>{{ count($items) }}</small></a>@endforeach
                        </div>
                    @endif
                    @foreach ($groups as $topic => $items)
                        <h2 class="h-2" id="topic-{{ \Illuminate\Support\Str::slug($topic) }}" style="margin:44px 0 12px">{{ $topic }}</h2>
                        @include('partials.faq', ['faqs' => $items])
                    @endforeach
                </div>
            </section>
        @endif

        @include('partials.photo-strip', ['items' => $strip, 'title' => 'Around the resort'])
        @if (! $legal)
            @foreach (array_slice($bands, 1, 1) ?: array_slice($bands, 0, 1) as $band)@include('partials.cta-band', ['band' => $band])@endforeach
            <section class="section--tight" style="padding-bottom:clamp(56px,8vw,110px)"><div class="wrap"><x-subscribe source="blog" variant="light" /></div></section>
        @else
            <div style="height:60px"></div>
        @endif
    </div>
@endsection

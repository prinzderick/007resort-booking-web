@use('App\Support\Text')
@use('App\Support\Lagos')
@extends('layouts.app')
@section('title', $post['title'])
@section('description', $post['seo']['description'] ?? $post['excerpt'])
@section('og_image', $post['seo']['ogImage']['url'] ?? ($post['cover']['url'] ?? ''))
@section('og_type', 'article')

@push('head')
    @include('partials.preload', ['m' => $post['cover'] ?? null])
    <x-jsonld :data="array_filter(['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'], 'description' => $post['excerpt'], 'image' => $post['cover']['url'] ?? null,
        'datePublished' => $post['publishedAt'], 'dateModified' => $post['updatedAt'] ?? $post['publishedAt'], 'mainEntityOfPage' => url()->current(),
        'author' => ['@type' => 'Person', 'name' => $post['authorName'] ?? $ctx->site()['brand']['name']], 'publisher' => ['@type' => 'Organization', 'name' => $ctx->site()['brand']['name'], 'url' => url('/')],
        'articleSection' => $post['category']['name'] ?? null, 'keywords' => implode(', ', $post['tags'] ?? [])])" />
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Journal', 'item' => route('blog.index')], ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => url()->current()]]]" />
@endpush

@section('content')
    <div class="reading-bar" data-reading-bar aria-hidden="true"></div>
    <article>
        <header class="post-hero wrap">
            <div class="post-head">
                @if (! empty($post['category']['name']))<a class="badge badge--sun" href="{{ route('blog.index', ['category' => $post['category']['slug']]) }}">{{ $post['category']['name'] }}</a>@endif
                <h1 class="reveal">{{ $post['title'] }}</h1>
                <p class="lede" style="margin:0 auto 18px">{{ $post['excerpt'] }}</p>
                <div class="post-meta"><span>{{ Lagos::parse($post['publishedAt'])->format('j F Y') }}</span><span>{{ $post['readingTimeMinutes'] ?? 3 }} min read</span>@if (! empty($post['authorName']))<span>By {{ $post['authorName'] }}</span>@endif</div>
            </div>
            @if (! empty($post['cover']))<div class="post-cover ph reveal reveal--scale"><x-img :m="$post['cover']" sizes="(min-width: 1200px) 1200px, 100vw" eager /></div>@endif
        </header>
        <div class="wrap">
            <div class="post-body prose" data-reading-target>{!! $post['bodyHtml'] !!}</div>
            <div class="post-body" style="margin-top:36px">
                @if (! empty($post['tags']))<div class="tag-list">@foreach ($post['tags'] as $t)<a class="tag" href="{{ route('blog.index', ['q' => $t]) }}">#{{ $t }}</a>@endforeach</div>@endif
                <div class="share" style="margin-top:22px" aria-label="Share this story"><span style="color:var(--mute);font-size:14px">Share</span>
                    <a href="https://wa.me/?text={{ rawurlencode($post['title'].' '.url()->current()) }}" rel="noopener">WhatsApp</a>
                    <a href="https://x.com/intent/post?text={{ rawurlencode($post['title']) }}&url={{ rawurlencode(url()->current()) }}" rel="noopener">X</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode(url()->current()) }}" rel="noopener">Facebook</a>
                    <button type="button" data-copy-link>Copy link</button>
                </div>
            </div>
            <div class="post-body" style="max-width:820px;margin-top:56px"><x-subscribe source="blog" variant="light" /></div>
        </div>
    </article>

    @if (count($related))
        <section class="section"><div class="wrap">
            <div class="sec-head"><h2 class="h-1">Keep <i>reading.</i></h2><a class="link-arrow" href="{{ route('blog.index') }}">All stories <span aria-hidden="true">&rarr;</span></a></div>
            <x-rail label="Related stories" grid>@foreach ($related as $p)<div style="display:flex">@include('partials.card-post', ['p' => $p])</div>@endforeach</x-rail>
        </div></section>
    @endif
@endsection

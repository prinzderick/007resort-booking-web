@use('App\Support\Text')
@use('App\Support\OpenHours')
@extends('layouts.app')
@section('title', Text::plain($page['title']))
@section('description', $page['seo']['description'] ?? $page['subtitle'] ?? '')
@section('hero', '1')

@php
    $s = $ctx->site();
    $c = $s['contact'];
    $open = $s['open'];
    $todayIso = now(config('r007.display_timezone'))->dayOfWeekIso;
    $embed = $c['mapEmbedUrl'] ?? null;
    if (! $embed && ! empty($c['lat']) && ! empty($c['lng'])) {
        $d = 0.012;
        $embed = 'https://www.openstreetmap.org/export/embed.html?bbox='.($c['lng'] - $d).'%2C'.($c['lat'] - $d).'%2C'.($c['lng'] + $d).'%2C'.($c['lat'] + $d).'&layer=mapnik&marker='.$c['lat'].'%2C'.$c['lng'];
    }
    $allowed = collect(config('cms.frame_hosts'))->contains(fn ($h) => str_starts_with((string) $embed, $h));
@endphp

@push('head')
    <x-jsonld :data="['@context' => 'https://schema.org', '@type' => 'ContactPage', 'name' => Text::plain($page['title']), 'url' => url()->current()]" />
@endpush

@section('content')
    @include('partials.page-hero', ['image' => $page['hero'] ?? $page['heroFallback'], 'title' => $page['title'], 'sub' => $page['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ['Contact']]])

    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section">
            <div class="wrap event-layout" style="align-items:start">
                <div>
                    @if (session('status'))<x-notice type="success">{{ session('status') }}</x-notice>@endif
                    @if ($errors->has('form'))<x-notice type="error">{{ $errors->first('form') }}</x-notice>@endif
                    <h2 class="h-2">Send us a message</h2>
                    <form class="panel" style="margin-top:20px" method="POST" action="{{ route('contact.send') }}" data-once>
                        @csrf
                        <x-spam />
                        <div class="form-grid">
                            <x-field name="name" label="Your name" autocomplete="name" />
                            <x-field name="email" label="Email" type="email" autocomplete="email" />
                            <x-field name="phone" label="Phone (optional)" type="tel" autocomplete="tel" :required="false" />
                            <div class="field"><label for="topic">What is it about?</label>
                                <select id="topic" name="topic">@foreach ($topics as $k => $label)<option value="{{ $k }}" @selected(old('topic', 'GENERAL') === $k)>{{ $label }}</option>@endforeach</select></div>
                            <div class="full"><x-field name="message" label="Message" type="textarea" :rows="6" hint="At least 10 characters." /></div>
                            <div class="full"><button class="btn btn--lg" type="submit" data-busy="Sending...">Send message <span class="arr" aria-hidden="true">&rarr;</span></button></div>
                        </div>
                    </form>
                </div>
                <aside class="stack-lg">
                    <dl class="info-list panel">
                        @if (! empty($c['address']))<div><dt>Address</dt><dd>{{ $c['address'] }}</dd></div>@endif
                        @if (! empty($c['phone']))<div><dt>Phone</dt><dd><a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}">{{ $c['phone'] }}</a></dd></div>@endif
                        @if (! empty($c['email']))<div><dt>Email</dt><dd><a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></dd></div>@endif
                        @if ($wa)<div><dt>WhatsApp</dt><dd><a class="btn wa btn--sm" href="{{ $wa }}" rel="noopener">Chat with us</a></dd></div>@endif
                    </dl>
                    @if ($open['known'])
                        <div class="panel">
                            <h2 class="h-3">Opening hours</h2>
                            <p class="badge {{ $open['open'] ? 'badge--ok' : 'badge--warn' }}" style="margin:10px 0" role="status">{{ $open['label'] }}</p>
                            <dl class="hours">
                                @foreach ($open['week'] as $w)
                                    <div class="{{ $w['iso'] === $todayIso ? 'is-today' : '' }}"><dt>{{ $w['name'] }}</dt><dd>{{ $w['closed'] ? 'Closed' : OpenHours::fmt($w['open']).' to '.OpenHours::fmt($w['close']) }}</dd></div>
                                @endforeach
                            </dl>
                            @if (! empty($s['hours']['notes']))<p class="hint" style="margin-top:12px;color:var(--mute);font-size:14px">{{ $s['hours']['notes'] }}</p>@endif
                        </div>
                    @endif
                </aside>
            </div>
        </section>

        @if ($embed && $allowed)
            <section class="section--tight"><div class="wrap"><div class="map"><iframe src="{{ $embed }}" title="Map to {{ $s['brand']['name'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
                @php $mapLink = $c['mapUrl'] ?? (! empty($c['lat']) ? 'https://www.openstreetmap.org/?mlat='.$c['lat'].'&mlon='.$c['lng'].'#map=15/'.$c['lat'].'/'.$c['lng'] : null); @endphp
                @if ($mapLink)<p style="margin-top:12px"><a class="link-arrow" href="{{ $mapLink }}" rel="noopener">Open in maps <span aria-hidden="true">&rarr;</span></a></p>@endif</div></section>
        @elseif (! empty($c['mapUrl']))
            <section class="section--tight"><div class="wrap"><a class="btn btn--line" href="{{ $c['mapUrl'] }}" rel="noopener">Open in maps</a></div></section>
        @endif

        @if (count($faqs))
            <section class="section section--paper2" aria-labelledby="faq-h"><div class="wrap"><div class="lead"><div><span class="eyebrow">Before you write</span><h2 id="faq-h" class="h-1">Quick <i>answers.</i></h2><p style="margin-top:14px"><a class="link-arrow" href="{{ route('faq') }}">All questions <span aria-hidden="true">&rarr;</span></a></p></div><div>@include('partials.faq', ['faqs' => $faqs])</div></div></div></section>
        @endif
        <div style="height:60px"></div>
    </div>
@endsection

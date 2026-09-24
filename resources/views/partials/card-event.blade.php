@use('App\Support\Lagos')
{{-- $e: event occurrence, $href: optional override --}}
@php $d = Lagos::parse($e['startsAt']); @endphp
<a class="card" href="{{ $href ?? route('events.show', $e['slug']).(! empty($e['isRecurring']) ? '?start='.urlencode($e['startsAt']) : '') }}">
    <div class="ph zoom"><x-img :m="$e['cover'] ?? null" sizes="(min-width: 900px) 30vw, 78vw" />
        <span class="badge">{{ ucfirst(strtolower($e['category'] ?? 'Event')) }}</span>
        <span class="card-date"><b>{{ $d->format('j') }}</b><span>{{ $d->format('M') }}</span></span>
    </div>
    <div class="card-body">
        <h3>{{ $e['title'] }}</h3>
        <div class="meta"><span class="tnum">{{ $d->format('D g:ia') }}</span>@if (! empty($e['venue']['label']))<span>{{ $e['venue']['label'] }}</span>@endif</div>
        <p>{{ $e['summary'] }}</p>
        @if (! empty($e['priceText']))<div class="meta"><b style="color:var(--ink)">{{ $e['priceText'] }}</b></div>@endif
    </div>
</a>

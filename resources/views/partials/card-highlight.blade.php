{{-- $h: HIGHLIGHT section --}}
<a class="card" href="{{ $h['link'] ?: '#' }}">
    <div class="ph zoom"><x-img :m="$h['media'] ?? null" sizes="(min-width: 900px) 30vw, 78vw" />@if (! empty($h['category']))<span class="badge">{{ ucfirst($h['category']) }}</span>@endif</div>
    <div class="card-body">
        <h3>{{ $h['title'] }}</h3>
        <p>{{ $h['blurb'] }}</p>
        @if (! empty($h['priceFrom']))<div class="meta"><b style="color:var(--ink)">{{ $h['priceFrom'] }}</b></div>@endif
    </div>
</a>

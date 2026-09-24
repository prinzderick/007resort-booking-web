@use('App\Support\Lagos')
<a class="card" href="{{ route('blog.show', $p['slug']) }}">
    <div class="ph zoom"><x-img :m="$p['cover'] ?? null" sizes="(min-width: 900px) 30vw, 78vw" />@if (! empty($p['category']['name']))<span class="badge">{{ $p['category']['name'] }}</span>@endif</div>
    <div class="card-body">
        <h3>{{ $p['title'] }}</h3>
        <p>{{ $p['excerpt'] }}</p>
        <div class="meta"><span>{{ Lagos::parse($p['publishedAt'])->format('j M Y') }}</span>@if (! empty($p['readingTimeMinutes']))<span>{{ $p['readingTimeMinutes'] }} min read</span>@endif</div>
    </div>
</a>

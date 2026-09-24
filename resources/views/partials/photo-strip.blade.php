{{-- Props: $items (album items), $title --}}
@if (count($items))
<section class="section--tight" aria-label="{{ $title ?? 'Photos' }}">
    <div class="wrap">
        <div class="sec-head"><h2 class="h-2 reveal">{{ $title ?? 'Around the resort' }}</h2><a class="link-arrow" href="{{ route('gallery') }}">Full gallery <span aria-hidden="true">&rarr;</span></a></div>
        <x-rail label="{{ $title ?? 'Photos' }}" col="min(62vw, 300px)">
            @foreach ($items as $it)
                <a class="ph zoom" style="aspect-ratio:3/4;border-radius:var(--r);display:block" href="{{ route('gallery') }}#photo={{ $it['id'] }}"><x-img :m="$it['media']" :alt="$it['alt'] ?? null" sizes="(min-width: 900px) 25vw, 62vw" /></a>
            @endforeach
        </x-rail>
    </div>
</section>
@endif

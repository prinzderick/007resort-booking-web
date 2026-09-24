@if (count($items))
<section class="section section--paper2" aria-labelledby="quotes-h">
    <div class="wrap">
        <div class="sec-head">
            <div><span class="eyebrow">Guests say</span><h2 id="quotes-h" class="h-1 reveal">Come once, <i>come back.</i></h2></div>
        </div>
        <div class="quotes">
            <x-rail label="Guest testimonials" col="min(86vw, 520px)" autoplay="7000">
                @foreach ($items as $t)
                    <figure class="quote reveal" style="--i:{{ $loop->index }}">
                        <div>
                            @if (! empty($t['rating']))<div class="stars" role="img" aria-label="{{ $t['rating'] }} out of 5 stars">{{ str_repeat('★', (int) $t['rating']) }}{{ str_repeat('☆', 5 - (int) $t['rating']) }}</div>@endif
                            <blockquote>{{ $t['quote'] }}</blockquote>
                        </div>
                        <footer>
                            @if (! empty($t['avatar']['url']))<span class="av"><x-img :m="$t['avatar']" sizes="48px" alt="" /></span>@endif
                            <span><b>{{ $t['name'] }}</b>@if (! empty($t['role']))<span>{{ $t['role'] }}</span>@endif</span>
                        </footer>
                    </figure>
                @endforeach
            </x-rail>
        </div>
    </div>
</section>
@endif

@if (count($posts))
<section class="section" aria-labelledby="journal-h">
    <div class="wrap">
        <div class="sec-head">
            <div><span class="eyebrow">From the journal</span><h2 id="journal-h" class="h-1 reveal">Stories from <i>the courts and the deck.</i></h2></div>
            <a class="link-arrow" href="{{ route('blog.index') }}">All stories <span aria-hidden="true">&rarr;</span></a>
        </div>
        <x-rail label="Latest stories" grid>
            @foreach ($posts as $p)<div class="reveal" style="--i:{{ $loop->index }};display:flex">@include('partials.card-post', ['p' => $p])</div>@endforeach
        </x-rail>
    </div>
</section>
@endif

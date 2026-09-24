@use('App\Support\Lagos')
@use('App\Support\Text')
{{-- Dark photo section with the upcoming-events list. Props: $events, $eyebrow, $title, $body, $image, $more --}}
@if (count($events))
<section class="night" aria-labelledby="night-h">
    <div class="ph" data-parallax="0.15"><x-img :m="$image ?? null" sizes="100vw" alt="" /></div>
    <div class="wrap night-in">
        <div class="reveal">
            <span class="eyebrow">{{ $eyebrow ?? 'This weekend' }}</span>
            <h2 id="night-h">{{ Text::accent($title ?? 'After dark, it gets better.') }}</h2>
            @if (! empty($body))<p class="lede">{{ $body }}</p>@endif
            <p style="margin-top:26px"><a class="btn btn--ghost" href="{{ route('events.index') }}" data-magnetic>All events <span class="arr" aria-hidden="true">&rarr;</span></a></p>
        </div>
        <div>
            @foreach ($events as $e)
                @php $d = Lagos::parse($e['startsAt']); @endphp
                <a class="ev reveal" style="--i:{{ $loop->index }}" href="{{ route('events.show', $e['slug']) }}{{ ! empty($e['isRecurring']) ? '?start='.urlencode($e['startsAt']) : '' }}">
                    <span class="t tnum">{{ $d->format('H:i') }}</span>
                    <div><b>{{ $e['title'] }}</b><span class="s">{{ $d->format('D j M') }} · {{ $e['venue']['label'] ?? '' }}</span></div>
                    @if (! empty($e['priceText']))<em>{{ $e['priceText'] }}</em>@endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

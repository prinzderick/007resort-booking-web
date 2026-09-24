@use('App\Support\Text')
@if (! empty($band))
<section class="section--tight" aria-label="{{ $band['title'] }}">
    <div class="wrap">
        <div class="cta-band reveal">
            <div>
                <h2>{{ Text::accent($band['title']) }}</h2>
                @if (! empty($band['text']))<p style="margin-top:12px;max-width:52ch;opacity:.95">{{ $band['text'] }}</p>@endif
            </div>
            <a class="btn btn--lg" href="{{ $band['ctaLink'] }}" data-magnetic>{{ $band['ctaLabel'] }} <span class="arr" aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</section>
@endif

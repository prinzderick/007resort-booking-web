@props(['source' => 'footer', 'variant' => 'dark', 'title' => null, 'text' => null, 'compact' => false])
@php $uid = 'sub-'.\Illuminate\Support\Str::random(5); @endphp
<section {{ $attributes->class(['subscribe', 'subscribe--light' => $variant === 'light', 'reveal' => ! $compact]) }} aria-labelledby="{{ $uid }}-h" data-subscribe>
    @unless ($compact)
        <div>
            <h3 id="{{ $uid }}-h" class="h-2">{{ $title ?? 'Get the weekend before it happens.' }}</h3>
            <p>{{ $text ?? 'One short email a week: what is on, new slots, member offers. Confirm once, leave any time.' }}</p>
        </div>
    @else
        <h3 id="{{ $uid }}-h" class="sr-only">Subscribe to updates</h3>
    @endunless
    <div>
        <form method="POST" action="{{ route('newsletter.subscribe') }}" novalidate data-subscribe-form>
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">
            <input type="hidden" name="consent" value="1">
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="sub-form">
                <label class="sr-only" for="{{ $uid }}-e">Email address</label>
                <input id="{{ $uid }}-e" type="email" name="email" required autocomplete="email" placeholder="you@example.com" value="{{ old('subscribe_email') }}">
                <button class="btn btn--sm" type="submit">Subscribe</button>
            </div>
            <p class="sub-msg" role="status" aria-live="polite" data-subscribe-msg>@if (session('subscribe_status')){{ session('subscribe_status') }}@endif</p>
            <p class="hint" style="font-size:12.5px;opacity:.7">By subscribing you agree to receive our emails. You confirm by email first, and can leave with one click.</p>
        </form>
    </div>
</section>

@use('App\Support\Money')
@use('App\Support\Text')
@extends('layouts.app')
@section('title', Text::plain($cms['title']))
@section('description', $cms['seo']['description'] ?? 'Join 007 Resort & Spa as a member for priority access and member rates.')
@section('hero', '1')

@section('content')
    @include('partials.page-hero', ['image' => $cms['hero'] ?? $cms['heroFallback'], 'eyebrow' => 'Membership', 'title' => $cms['title'], 'sub' => $cms['subtitle'], 'compact' => true, 'crumbs' => [['Home', route('home')], ['Membership']], 'ctas' => [['See plans', '#plans']]])
    <div class="slide-over" style="margin-top:0;border-radius:0">
        <section class="section" id="plans" style="padding-top:clamp(40px,6vw,80px)">
            <div class="wrap">
                <div class="sec-head"><div><span class="eyebrow">Plans</span><h2 class="h-1">Pick a <i>plan.</i></h2></div><p class="lede">Pay securely online. Your membership QR is available in your account as soon as payment is confirmed.</p></div>
                @if ($notice)<x-notice type="warn">{{ $notice }}</x-notice>@endif
                <div class="grid">
                    @foreach ($plans as $p)
                        <article class="plan reveal {{ $loop->index === 0 && count($plans) > 1 ? 'plan--feat' : '' }}" style="--i:{{ $loop->index }}">
                            <h2 class="h-3">{{ $p['name'] }}</h2>
                            <p class="price">{{ Money::format($p['price']) }}</p>
                            <ul>
                                <li>Valid for {{ $p['durationDays'] }} days</li>
                                <li>{{ ($p['visitLimit'] ?? null) ? $p['visitLimit'].' visits' : 'Unlimited visits' }}</li>
                            </ul>
                            <form method="POST" action="{{ route('memberships.buy', $p['id']) }}" data-once>
                                @csrf
                                <x-idem />
                                <button type="submit" data-busy="Redirecting to Paystack..." class="btn btn--block {{ $loop->index === 0 && count($plans) > 1 ? 'btn--light' : '' }}">Buy this plan</button>
                            </form>
                        </article>
                    @endforeach
                </div>
                @if (! $notice && count($plans) === 0)<p class="empty">No plans are on sale online right now. Please ask at reception.</p>@endif
            </div>
        </section>

        @if (trim($cms['bodyHtml'] ?? '') !== '')<section class="section section--paper2"><div class="wrap"><div class="narrow prose reveal">{!! $cms['bodyHtml'] !!}</div></div></section>@endif
        @if (count($faqs))
            <section class="section"><div class="wrap"><div class="lead"><div><span class="eyebrow">Questions</span><h2 class="h-1">Before you <i>join.</i></h2></div><div>@include('partials.faq', ['faqs' => $faqs, 'open' => true])</div></div></div></section>
        @endif
        <div style="height:40px"></div>
    </div>
@endsection

<?php

use App\Services\Cms\Fixtures\Stock as S;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

$now = CarbonImmutable::now('Africa/Lagos');
$cat = fn (string $n) => ['slug' => Str::slug($n), 'name' => $n];

$defs = [
    ['how-to-book-a-court-in-two-minutes', 'How to book a court in two minutes', 'Play', 'From pick to QR, step by step, plus what happens if the timer runs out.', 'tennis-02', 3, true,
        "Booking a court here is meant to take less time than finding your racket.\n\n## Pick the court and the hour\n\nOpen the **Sports** page, choose tennis, football or basketball, and the live availability shows straight away. Green slots are free, dark ones are taken. Tap the hour you want.\n\n## We hold it while you pay\n\nThe moment you choose a slot we hold it for ten minutes and show a countdown. That is enough time to sign in and pay with Paystack; if the timer runs out, the slot goes back on sale and nothing is charged.\n\n![Floodlit clay court at night](/stock/hero-03-floodlit-tennis-night-960.webp)\n\n## Walk in with a QR\n\nAfter payment your QR ticket appears on the confirmation page and in **My bookings**. Show it at the arena gate. Need a racket? The Sports Store will scan the same code.\n\n> The slot you see is the slot the front desk sees. There is one booking system, so nobody can sell your hour twice.\n\n## Change of plans\n\nOpen the booking from your account to cancel or move it. The refund rules for that exact slot are shown before you confirm."],
    ['a-slow-sunday-at-the-pool', 'A slow Sunday at the pool', 'Wellness', 'Where to sit, when to swim and what to order if you have no plans until Monday.', 'pool-04', 2, false,
        "The best hour at the pool is the first one. The water is still, the loungers along the palms are all free, and the bar is not yet asking for your order.\n\n## Where to sit\n\nThe loungers nearest the palms get shade by noon. The shallow end is best for families, and the deck by the DJ booth is where the afternoon ends up.\n\n## What to order\n\nSomething cold first. Then the grilled fish from the deck, which comes out in about fifteen minutes.\n\n## Passes\n\nBuy day passes online for the group and everyone gets their own QR, so nobody has to wait at the gate."],
    ['inside-the-grill-deck', 'Inside the grill deck: charcoal, pepper and patience', 'Food & Drink', 'Suya the slow way, and why the kitchen opens at five.', 'dining-02', 4, false,
        "The grill deck does one thing and does it properly: meat over charcoal, spiced in-house and turned by hand.\n\n## The spice\n\nThe suya mix is ground each morning: groundnut, ginger, pepper and a few things the cooks do not write down.\n\n## Why five o'clock\n\nCharcoal needs an hour to come to temperature, and we would rather open a little later than grill over a fire that is not ready.\n\n## Pair it with\n\nA cold Star, a bottle of water, and a seat on the deck where the string lights come on around seven."],
    ['what-a-massage-actually-does', 'What a hot stone massage actually does', 'Wellness', 'A therapist explains the warmth, the pressure and how to book the right length.', 'spa-01', 3, false,
        "Heat lets muscle relax before pressure is applied, which is why a hot stone session can feel deeper without feeling harder.\n\n## Choosing the length\n\nSixty minutes covers back, shoulders and legs. Ninety adds the feet and scalp and is the one people book twice.\n\n## Before you come\n\nDrink water, skip the heavy lunch, and arrive ten minutes early so the tea and robe are ready.\n\n## Aftercare\n\nHydrate, and give yourself the rest of the afternoon."],
    ['match-night-etiquette', 'Match night etiquette (a friendly guide)', 'Community', 'Where to stand, how loud to be and the unwritten rules of the big screen.', 'events-03', 2, false,
        "Match night is a shared living room the size of a bar. A few habits keep it fun.\n\n- Arrive early for a table; the best seats go by 7pm.\n- Sound stays on for everyone, so keep chants for the goals.\n- Members can reserve a table at reception.\n\nWhoever you support, the grill deck is open until the final whistle."],
    ['welcome-to-the-new-007-website', 'Welcome to the new 007 website', 'News', 'Everything in one place: courts, pool, spa, events and a QR for the door.', 'hero-04', 2, false,
        "We rebuilt the site so that everything you can do at 007 can be done from your phone: check court availability, buy pool passes, book a treatment, see what is on this weekend and keep your tickets in one account.\n\nIf something is missing or confusing, message us on WhatsApp and we will fix it."],
    ['tennis-for-beginners-your-first-hour', 'Tennis for beginners: your first hour', 'Play', 'Grip, stance and a few rallies, without feeling watched.', 'tennis-01', 4, false,
        "You do not need a lesson to start, only an hour and a partner.\n\n## Warm up\n\nMini rallies from the service line, ten each side, before you go back to the baseline.\n\n## Book a quiet slot\n\nMorning slots are the emptiest; the clay is soft and nobody is waiting on your court.\n\n## Borrow, do not buy\n\nRent a racket and balls at the Sports Store until you know what you like."],
];

$out = [];
foreach ($defs as $i => [$slug, $title, $c, $excerpt, $img, $mins, $featured, $md]) {
    $out[] = [
        'id' => "post-$slug", 'slug' => $slug, 'title' => $title, 'excerpt' => $excerpt, 'cover' => S::media($img),
        'authorName' => $i % 2 ? 'Amaka Ebi' : 'Tonye Harry', 'category' => $cat($c), 'tags' => [strtolower($c), 'resort'],
        'readingTimeMinutes' => $mins, 'featured' => $featured, 'publishedAt' => $now->subDays(3 + $i * 6)->utc()->format('Y-m-d\TH:i:s.000\Z'),
        'bodyMarkdown' => $md, 'seo' => ['title' => null, 'description' => $excerpt, 'ogImage' => S::media($img)], 'updatedAt' => '2026-09-10T09:00:00.000Z',
    ];
}

return $out;

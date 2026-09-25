<?php

use App\Services\Cms\Fixtures\Stock;

/** Shape = GET /public/cms/site (docs/CMS_API.md section 3). */
$days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

return [
    'brand' => ['name' => '007 Resort & Spa', 'tagline' => 'Everything you came for, in one place.', 'logo' => null],
    'contact' => [
        'phone' => '+234 803 000 0007', 'whatsapp' => '+2348030000007', 'email' => 'hello@007resort.example',
        'address' => '007 Resort & Spa, near the Federal University Otueke, Ogbia, Bayelsa State, Nigeria',
        'mapEmbedUrl' => 'https://www.openstreetmap.org/export/embed.html?bbox=6.31%2C4.78%2C6.33%2C4.80&layer=mapnik&marker=4.79%2C6.32',
        'lat' => 4.79, 'lng' => 6.32, // approximate, near the Federal University Otueke
    ],
    'hours' => [
        'weekly' => array_map(fn ($d) => ['day' => $d, 'open' => $d === 'SUN' ? '08:00' : '07:00', 'close' => in_array($d, ['FRI', 'SAT']) ? '23:59' : ($d === 'SUN' ? '22:00' : '23:00'), 'closed' => false], $days),
        'notes' => 'Sports arena floodlights stay on until closing. The grill deck kitchen opens at 5pm.',
        'holidays' => [],
    ],
    'social' => ['instagram' => 'https://instagram.com/007resort', 'facebook' => 'https://facebook.com/007resort', 'x' => 'https://x.com/007resort', 'tiktok' => 'https://tiktok.com/@007resort', 'youtube' => null],
    'seo' => [
        'titleTemplate' => '%s | 007 Resort & Spa',
        'defaultTitle' => '007 Resort & Spa | Sports, pool, spa and dining near the Federal University Otueke',
        'defaultDescription' => 'Book tennis, football and basketball courts, pool day passes, spa treatments and events at 007 Resort & Spa, Otueke. Instant QR confirmation.',
        'ogImage' => Stock::media('hero-01'),
    ],
    'announcement' => ['enabled' => true, 'text' => 'Sunset doubles every Saturday, 6pm on the tennis courts.', 'link' => '/events', 'tone' => 'PROMO'],
    'booking' => ['ticketsCtaLabel' => 'Buy pool tickets', 'bookingCtaLabel' => 'Book a court', 'membershipCtaLabel' => 'Join the club', 'eventsCtaLabel' => 'Get tickets'],
    'footer' => ['text' => 'A place to play hard, cool off and stay for dinner. Near the Federal University Otueke, Bayelsa State.', 'copyright' => '007 Resort & Spa. All rights reserved.'],
    'updatedAt' => '2026-09-20T09:00:00.000Z',
];

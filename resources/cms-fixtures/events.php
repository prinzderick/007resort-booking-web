<?php

use App\Services\Cms\Fixtures\Stock as S;
use Carbon\CarbonImmutable;

/**
 * Base events in the GET /public/cms/events item shape (before occurrence expansion). Dates are relative to now
 * (Africa/Lagos) so the dev site always has upcoming events across several months.
 */
$tz = 'Africa/Lagos';
$today = CarbonImmutable::now($tz)->startOfDay();
$nextDow = fn (int $dow) => (int) $today->diffInDays($today->next($dow));

// [slug, title, category, dayOffset, start, end, venue, summary, image, price, ticketUrl, weekly, featured, body]
$defs = [
    ['sunset-doubles', 'Sunset doubles', 'SPORT', $nextDow(6), '18:00', '20:00', 'Courts 1 and 2', 'Mixed doubles round robin as the floodlights come on.', 'tennis-02', 'From ₦8,000 a pair', '/book/sports-arena', true, true, "Bring a partner or come alone and we will pair you. Four rounds of short sets, a prize for the winning pair, and a cold drink for everyone at the end.\n\n- Courts 1 and 2, six pairs per round\n- Rackets and balls available at the Sports Store\n- Finals under the lights at 7:30pm"],
    ['match-night-big-screen', 'Match night on the big screen', 'OTHER', $nextDow(6), '20:00', '23:00', 'Terrace bar', 'Live football on the big screens, grill deck open.', 'events-03', 'Free entry', null, true, false, "Every big match, sound on, with the grill deck serving until kick-off and after the final whistle.\n\nTables are first come, first served. Members can reserve a table at reception."],
    ['poolside-dj', 'Poolside DJ', 'MUSIC', $nextDow(6), '21:00', '23:59', 'Pool deck', 'Afrobeats and amapiano by the water.', 'events-01', 'Free with a pool pass', '/pool', true, true, "A resident DJ on the pool deck from nine. Loungers stay open, the bar stays open, the water stays lit.\n\nBuy your pool pass online and walk straight in."],
    ['suya-and-shisha', 'Suya and shisha', 'FOOD', $nextDow(6), '22:00', '23:59', 'Grill deck', 'Charcoal suya, cold drinks, low music.', 'dining-02', 'Pay as you eat', null, true, false, 'Skewers straight off the coals, spiced the way you like them, on the grill deck under the string lights.'],
    ['sunrise-yoga', 'Sunrise yoga on the lawn', 'WELLNESS', $nextDow(0), '07:00', '08:00', 'Main lawn', 'An easy hour to start the week. Mats provided.', 'events-05', '₦3,000, members free', null, true, false, 'A slow, beginner-friendly flow on the lawn before the sun gets serious. Mats provided; bring water.'],
    ['kids-splash-day', 'Kids splash day', 'OTHER', $nextDow(0), '11:00', '15:00', 'Pool, shallow end', 'Games, floats and a lifeguard on every whistle.', 'pool-02', 'Child pass ₦2,000', '/pool', false, false, 'Organised games in the shallow end, a snack table and plenty of shade for parents. Child day passes apply.'],
    ['friday-basketball-run', 'Friday night basketball run', 'SPORT', $nextDow(5), '19:00', '22:00', 'Basketball court', 'Open runs, full court, mixed levels.', 'basketball-02', 'From ₦8,000 a court', '/book/sports-arena', true, false, 'Show up, get on a team, play until the lights go. Or book the whole court for your own group.'],
    ['five-a-side-cup', 'Five-a-side cup', 'SPORT', 10, '15:00', '20:00', 'Football pitch', 'Eight teams, one afternoon, one trophy.', 'football-03', '₦40,000 a team', null, false, true, 'Knockout format, seven-minute halves, referees provided. Register your team of five plus two subs at reception or by WhatsApp.'],
    ['brunch-and-celebration-viewing', 'Celebration viewing day', 'OTHER', 24, '11:00', '16:00', 'Event centre', 'See the terrace and event hall for your wedding, birthday or corporate day.', 'dining-07', 'Free viewing', null, false, false, 'Tell us the date and headcount and we will plan the room, the menu and the music. Viewings every Thursday afternoon.'],
    ['members-night', 'Members night', 'FOOD', 38, '19:00', '23:00', 'Indoor club', 'Priority tables, a tasting menu and the big screen.', 'events-04', 'Members free', '/membership', false, false, "A quarterly evening for members and a guest: tasting menu, live sport, and first look at the next season's courts schedule."],
    ['festival-of-lights', 'Festival of lights', 'MUSIC', 62, '18:00', '23:59', 'Whole resort', 'Live acts, lanterns and food stalls across the grounds.', 'events-07', 'Tickets soon', null, false, true, 'Our biggest night of the season. Stages on the lawn and pool deck, food from across the region, lanterns on the water at midnight.'],
];

$out = [];
foreach ($defs as $i => [$slug, $title, $cat, $d, $s, $e, $venue, $summary, $img, $price, $url, $weekly, $featured, $body]) {
    $start = $today->addDays($d)->setTimeFromTimeString($s);
    $end = $today->addDays($d)->setTimeFromTimeString($e);
    $out[] = [
        'id' => "event-$slug", 'slug' => $slug, 'title' => $title, 'summary' => $summary, 'cover' => S::media($img), 'category' => $cat,
        'startsAt' => $start->utc()->format('Y-m-d\TH:i:s.000\Z'), 'endsAt' => $end->utc()->format('Y-m-d\TH:i:s.000\Z'),
        'startsAtLocal' => $start->format('c'), 'endsAtLocal' => $end->format('c'), 'timezone' => $tz,
        'venue' => ['label' => $venue, 'facilityId' => null], 'priceText' => $price, 'capacity' => null,
        'ticket' => $url ? ['url' => $url, 'productId' => null, 'facilityId' => null] : null,
        'recurrence' => ['type' => $weekly ? 'WEEKLY' : 'NONE', 'until' => null], 'isRecurring' => (bool) $weekly,
        'featured' => (bool) $featured, 'publishedAt' => '2026-09-01T09:00:00.000Z',
        'bodyMarkdown' => $body, 'seo' => ['title' => null, 'description' => $summary, 'ogImage' => S::media($img)], 'updatedAt' => '2026-09-01T09:00:00.000Z',
    ];
}

return $out;

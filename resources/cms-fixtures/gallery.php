<?php

use App\Services\Cms\Fixtures\Stock as S;

/** Shape = GET /public/cms/gallery/albums(/{slug}). */
$albums = [
    'pool' => ['Pool', ['pool-01', 'pool-02', 'pool-03', 'pool-04', 'pool-05', 'pool-06', 'hero-01', 'hero-05'], 'Sunset from the deck, small people in shallow water, loungers under the palms.'],
    'sports' => ['Sports', ['tennis-01', 'tennis-02', 'tennis-03', 'tennis-04', 'tennis-05', 'basketball-01', 'basketball-02', 'basketball-03', 'basketball-04', 'football-01', 'football-02', 'football-03', 'padel-01', 'padel-02', 'hero-03'], 'Clay, hard court, floodlit pitch.'],
    'spa' => ['Spa', ['spa-01', 'spa-02', 'spa-03', 'spa-04', 'spa-05', 'spa-06'], 'Quiet rooms, warm stones, tea.'],
    'dining' => ['Food & drink', ['dining-01', 'dining-02', 'dining-03', 'dining-04', 'dining-05', 'dining-06', 'dining-07', 'dining-08', 'dining-09'], 'From the charcoal grill to the cocktail bar.'],
    'events' => ['Events', ['events-01', 'events-02', 'events-03', 'events-05', 'events-06', 'events-07', 'hero-06', 'hero-02'], 'Match nights, DJs and Sunday yoga.'],
    'grounds' => ['Grounds', ['gallery-01', 'gallery-02', 'gallery-03', 'gallery-04', 'gallery-05', 'hero-04', 'lifestyle-01', 'lifestyle-04'], 'Palms, festoon lights and the lobby.'],
];

$out = [];
$sort = 0;
foreach ($albums as $slug => [$title, $keys, $desc]) {
    $sort += 10;
    $items = [];
    foreach ($keys as $i => $k) {
        $m = S::media($k);
        $items[] = ['id' => "$slug-".($i + 1), 'media' => $m, 'caption' => null, 'alt' => $m['alt'], 'tags' => [], 'category' => $title, 'featured' => $i === 0, 'sortOrder' => ($i + 1) * 10];
    }
    $out[] = ['id' => "album-$slug", 'slug' => $slug, 'title' => $title, 'description' => $desc, 'cover' => S::media($keys[0]), 'itemCount' => count($items), 'sortOrder' => $sort, 'items' => $items];
}

return $out;

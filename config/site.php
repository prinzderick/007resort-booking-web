<?php

/*
| Static presentation content for the public site. Business data (hours,
| contact, prices, availability, whether online booking is currently possible)
| comes from the API and overlays this; the site still renders sensible pages
| when the API is unreachable.
|
| `match` lists API facility `kind`/`code` prefixes used to link a page to its
| API facility. `flow` selects the booking experience:
|   slots      pick resource -> date -> slot grid -> hold -> checkout
|   tickets    adult/child counts -> individual QR tickets
|   info       no online booking (visit / call)
*/

return [
    'name' => env('SITE_NAME', '007 Resort & Spa'),

    // Cross-document view transitions (smooth page-to-page fade in supporting browsers).
    'view_transitions' => filter_var(env('SITE_VIEW_TRANSITIONS', true), FILTER_VALIDATE_BOOL),

    // Navigation chrome (labels of the site's own routes). Page copy itself comes from the CMS.
    'nav' => [
        ['label' => 'Sports', 'href' => '/sports', 'hint' => 'Courts and pitch'],
        ['label' => 'Pool', 'href' => '/pool', 'hint' => 'Day passes'],
        ['label' => 'Spa', 'href' => '/spa', 'hint' => 'Treatments'],
        ['label' => 'Dining', 'href' => '/dining', 'hint' => 'Grill deck and bar'],
        ['label' => 'Events', 'href' => '/events', 'hint' => "What's on"],
        ['label' => 'Membership', 'href' => '/memberships', 'hint' => 'Plans'],
        ['label' => 'Journal', 'href' => '/blog', 'hint' => 'Stories'],
    ],

    // Shown in the home hero eyebrow.
    'location_line' => env('SITE_LOCATION_LINE', 'Near the Federal University Otueke, Bayelsa State'),

    'contact' => [
        'phone' => env('SITE_PHONE', '+234 000 000 0000'),
        'email' => env('SITE_EMAIL', 'hello@example.com'),
        'address' => env('SITE_ADDRESS', '007 Resort & Spa, near the Federal University Otueke, Ogbia, Bayelsa State, Nigeria'),
        'map_url' => env('SITE_MAP_URL'),
        'whatsapp' => env('SITE_WHATSAPP'),
    ],

    // Built-in fallbacks for CMS pages that editors have not created yet (title/subtitle only; everything else is CMS).
    'page_defaults' => [
        'sports' => ['title' => 'Sports', 'subtitle' => 'Book a court or pitch.', 'faq' => 'Booking', 'events' => 'SPORT', 'highlights' => 'play', 'album' => 'sports'],
        'pool' => ['title' => 'Pool day passes', 'subtitle' => 'Buy passes for the whole group.', 'faq' => 'Tickets', 'events' => null, 'highlights' => 'splash', 'album' => 'pool'],
        'spa' => ['title' => 'Spa and treatments', 'subtitle' => 'Book a treatment.', 'faq' => 'Booking', 'events' => 'WELLNESS', 'highlights' => 'reset', 'album' => 'spa'],
        'dining' => ['title' => 'Dining', 'subtitle' => 'Restaurant, grill deck and bar.', 'faq' => 'Visiting', 'events' => 'FOOD', 'highlights' => 'feast', 'album' => 'dining'],
        'membership' => ['title' => 'Membership', 'subtitle' => 'Pick a plan.', 'faq' => 'Membership', 'events' => null, 'highlights' => null, 'album' => null],
        'events' => ['title' => "What's on", 'subtitle' => 'Events at the resort.'],
        'blog' => ['title' => 'Journal', 'subtitle' => 'Stories and guides.'],
        'gallery' => ['title' => 'Gallery', 'subtitle' => 'Photos of the resort.'],
        'about' => ['title' => 'About us', 'subtitle' => ''],
        'contact' => ['title' => 'Contact', 'subtitle' => 'Get in touch.'],
        'faq' => ['title' => 'Questions', 'subtitle' => 'Good to know.'],
    ],

    // facility page slug -> CMS gallery album slug used for its photo strip
    'facility_albums' => ['sports-arena' => 'sports', 'pool' => 'pool', 'beauty-spa' => 'spa', 'salon' => 'spa', 'restaurant' => 'dining', 'bush-bar' => 'events', 'cafe' => 'dining', 'indoor-club' => 'events', 'supermarket' => 'grounds'],

    'default_hours' => 'Daily, 08:00 - 22:00',

    'facilities' => [
        'restaurant' => [
            'name' => 'Restaurant',
            'tagline' => 'Nigerian classics and continental favourites.',
            'description' => 'Sit-down dining with a kitchen that cooks to order. Ask reception about group tables and private dining.',
            'match' => ['RESTAURANT'],
            'flow' => 'info',
        ],
        'indoor-club' => [
            'name' => 'Indoor Club',
            'tagline' => 'Games, music and members-only evenings.',
            'description' => 'Our indoor club is the place for social evenings. Members enjoy priority entry and special rates.',
            'match' => ['INDOOR_CLUB', 'CLUB'],
            'flow' => 'info',
        ],
        'beauty-spa' => [
            'name' => 'Beauty Spa',
            'tagline' => 'Massage, facials and body treatments.',
            'description' => 'Book a treatment with a therapist of your choice. Appointments are held for you while you check out.',
            'match' => ['SPA', 'BEAUTY_SPA'],
            'flow' => 'slots',
            'noun' => 'treatment',
        ],
        'pool' => [
            'name' => 'Swimming Pool',
            'tagline' => 'Day passes for adults and children.',
            'description' => 'Buy day tickets online for your whole group. Every person gets an individual QR ticket for entry.',
            'match' => ['POOL'],
            'flow' => 'tickets',
        ],
        'sports-arena' => [
            'name' => 'Sports Arena',
            'tagline' => 'Tennis, football and more, by the hour.',
            'description' => 'Pick a court or pitch, see live availability and reserve it in minutes. Rentals are available at the sports store.',
            'match' => ['SPORTS', 'SPORTS_ARENA', 'COURT'],
            // Informational only (not sold through the hold): rentals collected at the Sports Store with the booking QR.
            'addons' => [['name' => 'Racket hire', 'note' => 'Sports Store, pay on arrival'], ['name' => 'Balls and bibs', 'note' => 'Sports Store, pay on arrival']],
            'flow' => 'slots',
            'noun' => 'court',
        ],
        'bush-bar' => [
            'name' => 'Bush Bar & Event Centre',
            'tagline' => 'Drinks, live events and celebrations.',
            'description' => 'An open-air bar and an event centre for weddings, birthdays and corporate functions. Contact us to plan yours.',
            'match' => ['BAR', 'BUSH_BAR', 'EVENT', 'EVENT_CENTRE'],
            'flow' => 'info',
        ],
        'salon' => [
            'name' => 'Salon',
            'tagline' => 'Hair, nails and grooming.',
            'description' => 'Reserve a stylist and time slot online and arrive to a chair that is ready for you.',
            'match' => ['SALON'],
            'flow' => 'slots',
            'noun' => 'service',
        ],
        'cafe' => [
            'name' => 'Cafe',
            'tagline' => 'Coffee, pastries and light bites.',
            'description' => 'A relaxed cafe for breakfast, coffee and quick lunches.',
            'match' => ['CAFE'],
            'flow' => 'info',
        ],
        'supermarket' => [
            'name' => 'Supermarket',
            'tagline' => 'Groceries and everyday essentials.',
            'description' => 'Stock up on groceries, drinks and toiletries without leaving the resort.',
            'match' => ['SUPERMARKET', 'MAIN_STORE'],
            'flow' => 'info',
        ],
    ],
];

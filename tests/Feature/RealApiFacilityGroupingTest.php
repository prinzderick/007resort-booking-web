<?php

namespace Tests\Feature;

use App\Services\Online\BookingService;
use App\Services\Online\SiteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\ApiTestCase;

/** Found while driving the site against the real API: one page can be several API facilities (a parent with children, or two salons). */
class RealApiFacilityGroupingTest extends ApiTestCase
{
    private function facilities(): array
    {
        return [
            ['id' => 'a-arena', 'parentId' => null, 'code' => 'SPORTS_ARENA', 'kind' => 'SPORTS', 'name' => 'Sports Arena', 'onlineBookable' => true],
            ['id' => 'a-tennis', 'parentId' => 'a-arena', 'code' => 'LAWN_TENNIS', 'kind' => 'SPORTS_VENUE', 'name' => 'Lawn Tennis', 'onlineBookable' => true],
            ['id' => 'a-female', 'parentId' => null, 'code' => 'SALON_FEMALE', 'kind' => 'SALON', 'name' => 'Female Salon', 'onlineBookable' => true],
            ['id' => 'a-male', 'parentId' => null, 'code' => 'SALON_MALE', 'kind' => 'SALON', 'name' => 'Male Salon', 'onlineBookable' => true],
        ];
    }

    public function test_pages_map_to_top_level_facilities_and_their_descendants(): void
    {
        Cache::flush();
        $this->fakeApi(['public/site' => Http::response(['contact' => [], 'facilities' => $this->facilities()], 200)]);
        $site = app(SiteService::class);

        $this->assertSame('a-arena', $site->facility('sports-arena')['id']);
        $this->assertEqualsCanonicalizing(['a-arena', 'a-tennis'], $site->facility('sports-arena')['ids']);
        $this->assertEqualsCanonicalizing(['a-female', 'a-male'], $site->facility('salon')['ids']);
        $this->assertSame('sports-arena', $site->slugForFacilityId('a-tennis'), 'a booking made on a child facility links back to its page');
    }

    public function test_resources_are_merged_across_the_facilities_of_a_page(): void
    {
        $this->fakeApi([
            'bookings/resources*' => fn ($r) => Http::response(['items' => [['id' => 'r-'.$r['facilityId'], 'name' => 'Chair', 'active' => true]], 'nextCursor' => null], 200),
        ]);

        $ids = array_column(app(BookingService::class)->resources(['a-female', 'a-male']), 'id');
        $this->assertSame(['r-a-female', 'r-a-male'], $ids);
    }
}

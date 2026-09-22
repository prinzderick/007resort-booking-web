<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_placeholder_home_page_lists_facilities(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Swimming pool')
            ->assertSee('Spa')
            ->assertSee('Sports');
    }
}

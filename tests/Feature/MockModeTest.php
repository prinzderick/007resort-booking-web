<?php

namespace Tests\Feature;

use App\Services\R007Api\MockR007ApiClient;
use App\Services\R007Api\R007ApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** R007_MOCK=true runs the entire customer journey without any backend. */
class MockModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['r007.mock' => true]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_mock_client_is_bound_when_enabled(): void
    {
        $this->assertInstanceOf(MockR007ApiClient::class, app(R007ApiClient::class));
    }

    public function test_full_journey_register_hold_pay_and_get_a_qr_ticket(): void
    {
        $this->get('/')->assertOk()->assertSee('Sports Arena');

        $this->post('/register', [
            'name' => 'Mock User', 'email' => 'mock@example.com', 'phone' => '+2348000000000', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
            '_ts' => Crypt::encryptString((string) (time() - 10)), 'website' => '',
        ])->assertRedirect();
        $this->post('/verify', ['email' => 'mock@example.com', 'code' => '123456'])->assertRedirect(route('account'));

        // Find an open slot tomorrow on the first court.
        $api = app(R007ApiClient::class);
        $court = $api->get('bookings/resources', ['facilityId' => '0192f6a0-0000-7000-8000-000000000101'])['items'][0];
        $day = now(config('r007.display_timezone'))->addDays(3)->startOfDay();
        $slots = $api->get("bookings/resources/{$court['id']}/availability", ['from' => $day->utc()->format('Y-m-d\TH:i:s\Z'), 'to' => $day->copy()->addDay()->utc()->format('Y-m-d\TH:i:s\Z')])['slots'];
        $slot = collect($slots)->firstWhere('available', true);

        $hold = $this->post("/book/sports-arena/{$court['id']}/hold", ['slot' => $slot['start'].'|'.$slot['end'], '_submission' => (string) Str::uuid()]);
        $hold->assertRedirect();
        $checkout = $hold->headers->get('Location');
        $this->get($checkout)->assertOk()->assertSee('data-countdown', false);

        $pay = $this->post($checkout.'/pay', ['_submission' => (string) Str::uuid()]);
        $paystack = $pay->headers->get('Location');
        $this->assertStringContainsString('/mock/paystack/', $paystack);
        $this->get($paystack)->assertOk()->assertSee('Mock API mode');

        $back = $this->post($paystack, ['outcome' => 'success'])->assertRedirect()->headers->get('Location');
        $ticket = $this->get($back)->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/tickets/', $ticket);
        $this->get($ticket)->assertOk()->assertSee('qr.svg', false);

        $this->get('/account/bookings')->assertOk()->assertSee('Confirmed');
    }

    public function test_mock_paystack_page_is_404_when_mock_mode_is_off(): void
    {
        config(['r007.mock' => false]);
        $this->get('/mock/paystack/ANY-REFERENCE')->assertNotFound();
    }
}

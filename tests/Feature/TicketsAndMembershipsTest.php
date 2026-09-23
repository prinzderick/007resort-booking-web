<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\ApiTestCase;

class TicketsAndMembershipsTest extends ApiTestCase
{
    private const ADULT = '0192f6a0-0000-7000-8000-000000000301';

    private const CHILD = '0192f6a0-0000-7000-8000-000000000302';

    private const ORDER = '0192f6a0-0000-7000-8000-000000000601';

    private function products(): array
    {
        return [
            ['id' => self::ADULT, 'name' => 'Pool day ticket - Adult', 'kind' => 'TICKET', 'price' => '3000.0000', 'active' => true],
            ['id' => self::CHILD, 'name' => 'Pool day ticket - Child', 'kind' => 'TICKET', 'price' => '1500.0000', 'active' => true],
        ];
    }

    public function test_pool_page_lists_adult_and_child_prices(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody()), 'catalog/products*' => $this->page($this->products())]);

        $this->get('/pool')->assertOk()->assertSee('Adult')->assertSee('Child')->assertSee('₦3,000')->assertSee('₦1,500')->assertSee('data-ticket-estimate', false);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'catalog/products') && $r->data()['facilityId'] === self::POOL && $r->data()['filter[kind]'] === 'TICKET');
    }

    public function test_pool_page_shows_notice_when_ticket_sales_are_paused(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody(['pool' => ['onlineBookable' => false]]))]);

        $this->get('/pool')->assertOk()->assertSee('Online ticket sales are temporarily unavailable')->assertDontSee('Continue to payment');
    }

    public function test_ticket_order_creates_an_api_order_and_redirects_to_paystack(): void
    {
        $this->fakeApi([
            'public/site' => Http::response($this->siteBody()),
            'public/ticket-orders' => Http::response(['id' => self::ORDER, 'total' => '7500.0000'], 201),
            'payments/paystack/initialize' => Http::response(['paymentId' => 'p', 'reference' => 'R007-POOL-1', 'authorizationUrl' => 'https://checkout.paystack.com/pool'], 201),
        ]);
        $date = now('Africa/Lagos')->addDay()->format('Y-m-d');
        $sub = (string) Str::uuid();

        $this->signIn()->post('/pool/order', ['date' => $date, 'qty' => [self::ADULT => 2, self::CHILD => 1], '_submission' => $sub])
            ->assertRedirect('https://checkout.paystack.com/pool')
            ->assertSessionHas('checkout.pending.R007-POOL-1.kind', 'tickets');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'public/ticket-orders') && $r['facilityId'] === self::POOL && $r['visitDate'] === $date
            && $r['lines'] === [['productId' => self::ADULT, 'quantity' => 2], ['productId' => self::CHILD, 'quantity' => 1]]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'paystack/initialize') && $r['orderIds'] === [self::ORDER] && $r['amount'] === '7500.0000');
    }

    public function test_ticket_order_requires_at_least_one_ticket(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);
        $date = now('Africa/Lagos')->addDay()->format('Y-m-d');

        $this->signIn()->post('/pool/order', ['date' => $date, 'qty' => [self::ADULT => 0, self::CHILD => 0], '_submission' => (string) Str::uuid()])->assertSessionHasErrors('qty');
        $this->assertSame([], $this->sentTo('POST', 'ticket-orders'));
    }

    public function test_ticket_order_rejects_past_dates(): void
    {
        $this->fakeApi(['public/site' => Http::response($this->siteBody())]);

        $this->signIn()->post('/pool/order', ['date' => '2020-01-01', 'qty' => [self::ADULT => 1], '_submission' => (string) Str::uuid()])->assertSessionHasErrors('date');
    }

    public function test_payment_return_lists_individual_qr_tickets_for_the_order(): void
    {
        $tickets = [$this->entitlement(['id' => '0192f6a0-0000-7000-8000-00000000a001', 'orderId' => self::ORDER]), $this->entitlement(['id' => '0192f6a0-0000-7000-8000-00000000a002', 'orderId' => self::ORDER])];
        $this->fakeApi([
            'payments/paystack/verify/R007-POOL-1' => Http::response(['status' => 'CAPTURED']),
            'customer/entitlements*' => $this->page($tickets),
        ]);

        $this->signIn()->pending('R007-POOL-1', ['kind' => 'tickets', 'id' => self::ORDER])
            ->get('/payment/return?reference=R007-POOL-1')->assertRedirect(route('tickets.order', self::ORDER));

        $this->get('/orders/'.self::ORDER.'/tickets')->assertOk()->assertSee('Your tickets (2)')->assertSee('tickets/0192f6a0-0000-7000-8000-00000000a002/qr.svg', false);
    }

    public function test_tickets_not_issued_yet_keeps_polling(): void
    {
        $this->fakeApi(['payments/paystack/verify/R007-POOL-1' => Http::response(['status' => 'CAPTURED']), 'customer/entitlements*' => $this->page([])]);

        $this->signIn()->pending('R007-POOL-1', ['kind' => 'tickets', 'id' => self::ORDER])->get('/payment/return?reference=R007-POOL-1')->assertSee('Confirming your payment');
    }

    public function test_ticket_page_renders_qr_svg_and_download(): void
    {
        $this->fakeApi(['entitlements/'.self::ENT => Http::response($this->entitlement())]);

        $this->signIn()->get('/tickets/'.self::ENT)->assertOk()->assertSee('Tennis Court 1 - 09:00')->assertSee('qr.svg', false)->assertSee('Download QR image');

        $svg = $this->get('/tickets/'.self::ENT.'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svg->getContent());

        $this->get('/tickets/'.self::ENT.'/download')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="007-resort-ticket-00000801.svg"');
    }

    public function test_someone_elses_ticket_is_a_404_not_a_leak(): void
    {
        $this->fakeApi(['entitlements/*' => $this->problemResponse(403, 'permission_denied')]);

        $this->signIn()->get('/tickets/'.self::ENT)->assertNotFound();
    }

    public function test_membership_plans_and_purchase(): void
    {
        $plan = ['id' => '0192f6a0-0000-7000-8000-000000000401', 'name' => 'Gold Monthly', 'durationDays' => 30, 'price' => '30000.0000', 'active' => true, 'visitLimit' => null];
        $this->fakeApi([
            'memberships/plans*' => $this->page([$plan]),
            'memberships' => Http::response(['id' => '0192f6a0-0000-7000-8000-000000000402', 'status' => 'PENDING_PAYMENT'], 201),
            'payments/paystack/initialize' => Http::response(['paymentId' => 'p', 'reference' => 'R007-MEM-1', 'authorizationUrl' => 'https://checkout.paystack.com/mem'], 201),
        ]);

        $this->get('/memberships')->assertOk()->assertSee('Gold Monthly')->assertSee('₦30,000')->assertSee('Unlimited visits');

        $this->signIn()->post('/memberships/'.$plan['id'].'/buy', ['_submission' => (string) Str::uuid()])->assertRedirect('https://checkout.paystack.com/mem');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/memberships') && $r->method() === 'POST' && $r['planId'] === $plan['id'] && $r['customer']['name'] === 'Chinedu Eze');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'paystack/initialize') && $r['membershipId'] === '0192f6a0-0000-7000-8000-000000000402' && $r['amount'] === '30000.0000');
    }

    public function test_membership_return_activates_only_when_the_api_says_active(): void
    {
        $mid = '0192f6a0-0000-7000-8000-000000000402';
        $this->fakeApi([
            'payments/paystack/verify/R007-MEM-1' => Http::response(['status' => 'CAPTURED']),
            'memberships/'.$mid => Http::sequence()->push(['id' => $mid, 'status' => 'PENDING_PAYMENT'])->push(['id' => $mid, 'status' => 'ACTIVE', 'planName' => 'Gold Monthly', 'validUntil' => '2026-10-23T00:00:00Z']),
        ]);

        $this->signIn()->pending('R007-MEM-1', ['kind' => 'membership', 'id' => $mid])->get('/payment/return?reference=R007-MEM-1')->assertSee('Confirming your payment');
        $this->get('/payment/return?reference=R007-MEM-1')->assertSee('Welcome, member')->assertSee('Gold Monthly');
    }

    public function test_membership_plans_degrade_with_a_notice(): void
    {
        $this->fakeApi(['memberships/plans*' => Http::response('', 503)]);

        $this->get('/memberships')->assertOk()->assertSee('temporarily unavailable');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesGuestCheckout;
use App\Services\Guest\GuestCheckoutApi;
use App\Services\Online\ContentService;
use App\Services\Online\CustomerService;
use App\Services\Online\MembershipService;
use App\Services\Online\PaymentService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\GuestDetails;
use App\Support\IdempotentSubmit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    use HandlesGuestCheckout;

    public function __construct(
        private readonly MembershipService $memberships,
        private readonly PaymentService $payments,
        private readonly CustomerService $customers,
        private readonly ContentService $content,
        private readonly GuestCheckoutApi $guestApi,
    ) {}

    public function index()
    {
        $plans = [];
        $notice = null;

        try {
            $plans = $this->memberships->plans();
        } catch (R007ApiException $e) {
            if (! $e->isUnavailable()) {
                throw $e;
            }
            $notice = 'Membership plans are temporarily unavailable online. Please ask at reception.';
        }

        return view('memberships.index', [
            'plans' => $plans, 'notice' => $notice, 'accountMode' => $this->accountMode(),
            'cms' => $this->content->page('membership'),
            'faqs' => $this->content->faqs('Membership', 3),
        ]);
    }

    /** "Your details" step for a membership plan. */
    public function checkout(Request $request, string $planId)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $planId) === 1, 404);
        if ($this->accountMode()) {
            return redirect()->route('memberships.index');
        }
        $plan = $this->plan($planId);
        if ($plan instanceof RedirectResponse) {
            return $plan;
        }

        return view('memberships.checkout', ['plan' => $plan, 'prefill' => $this->prefill($request)]);
    }

    public function buy(Request $request, string $planId)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $planId) === 1, 404);

        if (! $this->accountMode()) {
            $plan = $this->plan($planId);
            if ($plan instanceof RedirectResponse) {
                return $plan;
            }
            $guest = GuestDetails::validate($request);

            return IdempotentSubmit::run($request, "membership:{$planId}", function (string $key) use ($planId, $guest, $request, $plan) {
                try {
                    $start = $this->guestApi->startMembership($planId, (string) $plan['price'], $guest, $key);
                } catch (R007ApiException $e) {
                    return redirect()->route('checkout.membership', $planId)->withInput($request->except('_token'))->with('error', ApiProblem::message($e));
                }

                return $this->beginGuestPayment($request, $start, $guest, $key, 'membership');
            });
        }

        $user = $this->customers->user() ?? [];

        return IdempotentSubmit::run($request, "membership:{$planId}", function (string $key) use ($planId, $user, $request) {
            try {
                $plan = collect($this->memberships->plans())->firstWhere('id', $planId);
                abort_if($plan === null, 404);
                $membership = $this->memberships->start($planId, array_filter([
                    'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null, 'phone' => $user['phone'] ?? null,
                ]), $key);
                $init = $this->payments->initialize('membershipId', $membership['id'], (string) $plan['price'], (string) ($user['email'] ?? ''), route('payment.return'), $key.':pay');
            } catch (R007ApiException $e) {
                return redirect()->route('memberships.index')->with('error', ApiProblem::message($e));
            }

            $request->session()->put('checkout.pending.'.$init['reference'], ['kind' => 'membership', 'id' => $membership['id'], 'attempts' => 0]);

            return redirect()->away($init['authorizationUrl']);
        });
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function plan(string $planId)
    {
        try {
            $plan = collect($this->memberships->plans())->firstWhere('id', $planId);
        } catch (R007ApiException $e) {
            if (! $e->isUnavailable()) {
                throw $e;
            }

            return redirect()->route('memberships.index')->with('error', 'Membership plans are temporarily unavailable online. Please ask at reception.');
        }
        abort_if($plan === null, 404);

        return $plan;
    }
}

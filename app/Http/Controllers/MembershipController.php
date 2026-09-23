<?php

namespace App\Http\Controllers;

use App\Services\Online\CustomerService;
use App\Services\Online\MembershipService;
use App\Services\Online\PaymentService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\IdempotentSubmit;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly PaymentService $payments,
        private readonly CustomerService $customers,
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

        return view('memberships.index', compact('plans', 'notice'));
    }

    public function buy(Request $request, string $planId)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $planId) === 1, 404);
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
}

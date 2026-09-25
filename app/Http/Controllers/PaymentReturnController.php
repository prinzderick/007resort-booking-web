<?php

namespace App\Http\Controllers;

use App\Services\Guest\GuestCheckoutApi;
use App\Services\Guest\GuestSession;
use App\Services\Online\BookingService;
use App\Services\Online\CustomerService;
use App\Services\Online\MembershipService;
use App\Services\Online\PaymentService;
use App\Services\Online\TicketService;
use App\Services\R007Api\R007ApiException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where Paystack sends the customer back. The query string is only a lookup
 * key: the outcome is ALWAYS obtained by asking the API to verify the payment
 * (the API talks to Paystack; the webhook is handled there, never here).
 */
class PaymentReturnController extends Controller
{
    private const MAX_POLLS = 12;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly BookingService $bookings,
        private readonly TicketService $tickets,
        private readonly MembershipService $memberships,
        private readonly GuestCheckoutApi $guestApi,
        private readonly GuestSession $guests,
        private readonly CustomerService $customers,
    ) {}

    public function __invoke(Request $request)
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref'));
        if (! preg_match('/^[A-Za-z0-9_.=\-]{6,100}$/', $reference)) {
            return view('payment.result', ['state' => 'unknown']);
        }

        $key = 'checkout.pending.'.$reference;
        $pending = $request->session()->get($key);

        // Guests (and anyone whose payment started as a guest order) never need an account to land here.
        if ((is_array($pending) && ($pending['kind'] ?? '') === 'guest') || ! $this->customers->check()) {
            return $this->guestReturn($request, $reference, $key, is_array($pending) ? $pending : null);
        }

        try {
            $payment = $this->payments->verify($reference);
        } catch (R007ApiException $e) {
            if ($e->status === 404) {
                return view('payment.result', ['state' => 'unknown']);
            }
            if ($e->isUnavailable()) {
                return view('payment.result', ['state' => 'unverified', 'reference' => $reference]);
            }
            throw $e;
        }

        $status = (string) ($payment['status'] ?? '');

        if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            $request->session()->forget($key);

            return view('payment.result', ['state' => 'failed', 'pending' => $pending]);
        }

        if ($status !== 'CAPTURED') {
            return $this->processing($request, $key, $pending, $reference);
        }

        if (! is_array($pending)) {
            // Verified elsewhere (other browser/session): nothing to finalise here.
            return view('payment.result', ['state' => 'paid_unlinked']);
        }

        try {
            $outcome = match ($pending['kind']) {
                'booking' => $this->finaliseBooking($pending['id'], $reference),
                'tickets' => $this->finaliseTickets($pending['id']),
                'membership' => $this->finaliseMembership($pending['id']),
                default => null,
            };
        } catch (R007ApiException $e) {
            if (! $e->isUnavailable()) {
                throw $e;
            }
            $outcome = null;
        }

        if ($outcome === null) {
            return $this->processing($request, $key, $pending, $reference);
        }

        $request->session()->forget($key);

        return $outcome;
    }

    /** @return RedirectResponse|View|null */
    private function finaliseBooking(string $bookingId, string $reference)
    {
        $booking = $this->bookings->get($bookingId);

        // The API normally confirms on PaymentCaptured; if it has not yet, ask it to.
        if (in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true)) {
            try {
                $booking = $this->bookings->confirmWithPaystack($booking, $reference);
            } catch (R007ApiException $e) {
                if ($e->status !== 409 && $e->status !== 412) {
                    throw $e;
                }
                $booking = $this->bookings->get($bookingId);
            }
        }

        if ($booking['status'] === 'CONFIRMED' && ! empty($booking['entitlementId'])) {
            return redirect()->route('tickets.show', $booking['entitlementId'])->with('status', 'Payment received. Your booking is confirmed.');
        }
        if ($booking['status'] === 'CONFIRMED') {
            return redirect()->route('account.bookings.show', $bookingId)->with('status', 'Payment received. Your booking is confirmed.');
        }
        if (in_array($booking['status'], ['EXPIRED', 'CANCELLED'], true)) {
            // Paid but the hold was lost: never hide this, the API/finance handles the refund.
            return view('payment.result', ['state' => 'paid_hold_lost']);
        }

        return null;
    }

    private function finaliseTickets(string $orderId)
    {
        $tickets = $this->tickets->forSource('orderId', $orderId);

        return $tickets === [] ? null : redirect()->route('tickets.order', $orderId)->with('status', 'Payment received. Your tickets are ready.');
    }

    private function finaliseMembership(string $membershipId)
    {
        $membership = $this->memberships->get($membershipId);

        return ($membership['status'] ?? '') === 'ACTIVE'
            ? view('payment.result', ['state' => 'membership', 'membership' => $membership])
            : null;
    }

    /** @param  array<string, mixed>|null  $pending */
    private function processing(Request $request, string $key, ?array $pending, string $reference)
    {
        $attempts = (int) ($pending['attempts'] ?? 0) + 1;
        if (is_array($pending)) {
            $request->session()->put($key, ['attempts' => $attempts] + $pending);
        }

        return view('payment.result', [
            'state' => $attempts > self::MAX_POLLS ? 'delayed' : 'processing',
            'reference' => $reference,
        ]);
    }

    /**
     * Guest return: same rule as everywhere - the query string is only a lookup key, the API's verify decides.
     * On success the visitor goes to their order page (their access token is already in this browser's session).
     *
     * @param  array<string, mixed>|null  $pending
     */
    private function guestReturn(Request $request, string $key0, string $key, ?array $pending)
    {
        $reference = $key0;
        try {
            $payment = $this->guestApi->verifyPayment($reference);
        } catch (R007ApiException $e) {
            if ($e->status === 404) {
                return view('payment.result', ['state' => 'unknown']);
            }
            if ($e->isUnavailable()) {
                return view('payment.result', ['state' => 'unverified', 'reference' => $reference, 'order' => $pending['order'] ?? null]);
            }
            throw $e;
        }

        $status = (string) ($payment['status'] ?? '');
        $order = $pending['order'] ?? null;

        if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            // The order is NOT lost: it stays in this browser and can be paid again from its own page.
            $request->session()->forget($key);

            return view('payment.result', ['state' => 'failed', 'pending' => $pending, 'order' => $order]);
        }

        if ($status !== 'CAPTURED') {
            return $this->processing($request, $key, $pending, $reference);
        }

        if ($order === null) {
            // Paid from another browser or the session was lost: nothing to open here.
            return view('payment.result', ['state' => 'paid_unlinked']);
        }

        $request->session()->forget($key);

        return redirect()->route('orders.show', $order)->with('just_paid', true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Online\BookingService;
use App\Services\Online\CustomerService;
use App\Services\Online\MembershipService;
use App\Services\Online\SiteService;
use App\Services\Online\TicketService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\IdempotentSubmit;
use App\Support\Lagos;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly BookingService $bookings,
        private readonly MembershipService $memberships,
        private readonly TicketService $tickets,
        private readonly SiteService $site,
    ) {}

    public function dashboard()
    {
        [$bookings, $bookingsError] = $this->safe(fn () => $this->bookings->mine());
        [$memberships, $membershipsError] = $this->safe(fn () => $this->memberships->mine());

        $now = CarbonImmutable::now();
        $upcoming = array_values(array_filter($bookings, fn ($b) => in_array($b['status'], ['CONFIRMED', 'HELD', 'PENDING_PAYMENT', 'RESCHEDULED'], true)
            && CarbonImmutable::parse($b['end'])->gt($now)));

        return view('account.dashboard', [
            'user' => $this->customers->user(),
            'upcoming' => array_slice($upcoming, 0, 5),
            'memberships' => $memberships,
            'unavailable' => $bookingsError || $membershipsError,
        ]);
    }

    public function bookings()
    {
        [$bookings, $error] = $this->safe(fn () => $this->bookings->mine());
        usort($bookings, fn ($a, $b) => strcmp((string) $b['start'], (string) $a['start']));

        return view('account.bookings', ['bookings' => $bookings, 'unavailable' => $error]);
    }

    public function show(string $bookingId)
    {
        $booking = $this->loadOr404($bookingId);
        $entitlement = null;
        if (! empty($booking['entitlementId'])) {
            [$entitlement] = $this->safe(fn () => $this->tickets->entitlement($booking['entitlementId']), null);
        }

        return view('account.booking', [
            'booking' => $booking,
            'entitlement' => $entitlement,
            'rules' => $this->rules($booking),
        ]);
    }

    public function cancel(Request $request, string $bookingId)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return IdempotentSubmit::run($request, "cancel:{$bookingId}", function (string $key) use ($bookingId, $data) {
            try {
                $this->bookings->cancel($this->loadOr404($bookingId), ($data['reason'] ?? null) ?: 'Cancelled by customer', $key);
            } catch (R007ApiException $e) {
                return redirect()->route('account.bookings.show', $bookingId)->with('error', ApiProblem::message($e));
            }

            return redirect()->route('account.bookings.show', $bookingId)->with('status', 'Your booking has been cancelled. Any refund follows the cancellation policy.');
        });
    }

    public function rescheduleForm(Request $request, string $bookingId)
    {
        $booking = $this->loadOr404($bookingId);
        $today = Lagos::today();
        $day = Lagos::dayStart((string) $request->query('date')) ?? $today;
        $day = $day < $today ? $today : $day;
        $slots = [];
        $notice = null;

        try {
            $slots = $this->bookings->slots($booking['resourceId'], $day);
        } catch (R007ApiException $e) {
            if (! $e->isUnavailable()) {
                throw $e;
            }
            $notice = 'Rescheduling is temporarily unavailable online. Please call reception.';
        }

        return view('account.reschedule', [
            'booking' => $booking, 'slots' => $slots, 'day' => $day, 'notice' => $notice,
            'days' => array_map(fn ($i) => $today->addDays($i), range(0, 13)),
        ]);
    }

    public function reschedule(Request $request, string $bookingId)
    {
        $data = $request->validate([
            'slot' => ['required', 'string', 'regex:/^[0-9T:\-]+Z\|[0-9T:\-]+Z$/'],
        ]);
        [$start, $end] = explode('|', $data['slot']);

        return IdempotentSubmit::run($request, "reschedule:{$bookingId}", function (string $key) use ($bookingId, $start, $end) {
            try {
                $this->bookings->reschedule($this->loadOr404($bookingId), $start, $end, null, $key);
            } catch (R007ApiException $e) {
                return redirect()->route('account.bookings.reschedule', $bookingId)->with('error', ApiProblem::message($e));
            }

            return redirect()->route('account.bookings.show', $bookingId)->with('status', 'Your booking has been moved to the new time.');
        });
    }

    // ---- helpers ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function loadOr404(string $bookingId): array
    {
        try {
            return $this->bookings->get($bookingId);
        } catch (R007ApiException $e) {
            abort_if(in_array($e->status, [403, 404], true), 404);
            throw $e;
        }
    }

    /**
     * Cancel/reschedule eligibility is whatever the API says (`policy`); when it
     * says nothing we offer the action for future confirmed bookings and let the
     * API accept or refuse it.
     *
     * @param  array<string, mixed>  $booking
     * @return array<string, mixed>
     */
    private function rules(array $booking): array
    {
        $policy = (array) ($booking['policy'] ?? []);
        $active = $booking['status'] === 'CONFIRMED' && CarbonImmutable::parse($booking['start'])->isFuture();

        return [
            'canCancel' => (bool) ($policy['canCancel'] ?? $active),
            'canReschedule' => (bool) ($policy['canReschedule'] ?? $active),
            'cancelBy' => $policy['cancelBy'] ?? null,
            'refundAmount' => $policy['refundAmount'] ?? null,
            'note' => $policy['note'] ?? null,
        ];
    }

    /**
     * @return array{0: mixed, 1: bool}
     */
    private function safe(\Closure $fn, mixed $default = []): array
    {
        try {
            return [$fn(), false];
        } catch (R007ApiException $e) {
            if ($e->isUnavailable()) {
                return [$default, true];
            }
            throw $e;
        }
    }
}

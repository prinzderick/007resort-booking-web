<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesGuestCheckout;
use App\Services\Guest\GuestCheckoutApi;
use App\Services\Guest\GuestSession;
use App\Services\Online\BookingService;
use App\Services\Online\CustomerService;
use App\Services\Online\PaymentService;
use App\Services\Online\SiteService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\GuestDetails;
use App\Support\IdempotentSubmit;
use App\Support\Lagos;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Court / spa / salon booking: resource -> date -> slot grid -> hold ->
 * checkout -> Paystack. All availability and hold decisions are the API's.
 */
class BookingController extends Controller
{
    use HandlesGuestCheckout;

    public function __construct(
        private readonly SiteService $site,
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
        private readonly CustomerService $customers,
        private readonly GuestCheckoutApi $guestApi,
        private readonly GuestSession $guests,
    ) {}

    public function resources(string $slug)
    {
        $facility = $this->slotFacility($slug);
        $resources = [];
        $notice = $this->facilityNotice($facility);

        if ($notice === null) {
            try {
                $resources = $this->bookings->resources($facility['ids'] ?? [$facility['id']]);
            } catch (R007ApiException $e) {
                $notice = $this->unavailableNotice($e, $facility);
            }
        }

        return view('booking.resources', compact('facility', 'resources', 'notice'));
    }

    public function slots(Request $request, string $slug, string $resourceId)
    {
        $facility = $this->slotFacility($slug);
        $day = $this->requestedDay($request);
        $resource = null;
        $slots = [];
        $siblings = [];
        $notice = $this->facilityNotice($facility);

        if ($notice === null) {
            try {
                $siblings = $this->bookings->resources($facility['ids'] ?? [$facility['id']]);
                $resource = collect($siblings)->firstWhere('id', $resourceId);
                abort_if($resource === null, 404);
                if (($resource['onlineAvailable'] ?? true) === false) {
                    $notice = $resource['onlineNotice'] ?? 'Online booking for this item is temporarily unavailable. Please call or visit reception.';
                } else {
                    $slots = $this->bookings->slots($resourceId, $day);
                }
            } catch (R007ApiException $e) {
                if ($e->status === 404) {
                    abort(404);
                }
                $notice = $this->unavailableNotice($e, $facility);
            }
        }

        return view('booking.slots', [
            'facility' => $facility,
            'resource' => $resource,
            'slots' => $slots,
            'day' => $day,
            'days' => $this->days(),
            'notice' => $notice,
            'siblings' => $siblings,
        ]);
    }

    public function hold(Request $request, string $slug, string $resourceId)
    {
        $facility = $this->slotFacility($slug);
        $data = $request->validate([
            'slot' => ['required', 'string', 'regex:/^[0-9T:\-]+Z\|[0-9T:\-]+Z$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        [$start, $end] = explode('|', $data['slot']);
        $back = route('book.slots', [$slug, $resourceId, 'date' => Lagos::parse($start)?->format('Y-m-d')]);
        // Signed-in customers with a complete profile hold under their account (as always); everyone
        // else holds as a guest: no login, no account.
        $account = $this->accountMode();
        $user = $account ? ($this->customers->user() ?? []) : [];

        return IdempotentSubmit::run($request, "hold:{$resourceId}", function (string $key) use ($resourceId, $start, $end, $data, $user, $back, $account) {
            try {
                $booking = $this->bookings->hold($resourceId, $start, $end, (int) ($data['quantity'] ?? 1), array_filter([
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'phone' => $user['phone'] ?? null,
                ]), $key, guest: ! $account);
            } catch (R007ApiException $e) {
                // 409 slot_unavailable: back to a freshly loaded grid.
                return redirect()->to($back)->with('error', ApiProblem::message($e));
            }
            if (! $account) {
                $this->guests->rememberHold($booking['id']);
            }

            return redirect()->route('checkout.show', $booking['id']);
        });
    }

    public function checkout(Request $request, string $bookingId)
    {
        abort_unless(Str::isUuid($bookingId), 404);

        if ($this->guestPath($bookingId)) {
            $hold = $this->loadHold($bookingId);
            if ($hold instanceof RedirectResponse) {
                return $hold;
            }

            return view('booking.checkout', [
                'booking' => $hold,
                'secondsLeft' => $this->secondsLeft($hold),
                'slug' => $this->site->slugForFacilityId((string) ($hold['facilityId'] ?? '')),
                'account' => null,
                'prefill' => $this->prefill($request),
            ]);
        }

        $booking = $this->loadBooking($bookingId);
        if ($booking instanceof RedirectResponse) {
            return $booking;
        }

        return view('booking.checkout', [
            'booking' => $booking,
            'secondsLeft' => $this->secondsLeft($booking),
            'slug' => $this->site->slugForFacilityId((string) ($booking['facilityId'] ?? '')),
            'account' => GuestDetails::completeProfile($this->customers->user()),
            'prefill' => null,
        ]);
    }

    public function pay(Request $request, string $bookingId)
    {
        abort_unless(Str::isUuid($bookingId), 404);

        if ($this->guestPath($bookingId)) {
            $guest = GuestDetails::validate($request);

            return IdempotentSubmit::run($request, "pay:{$bookingId}", function (string $key) use ($bookingId, $guest, $request) {
                $hold = $this->loadHold($bookingId);
                if ($hold instanceof RedirectResponse) {
                    return $hold;
                }
                if ($this->secondsLeft($hold) <= 0) {
                    return redirect()->route('checkout.show', $bookingId);
                }

                try {
                    $payment = $this->guestApi->payBooking($bookingId, $guest, route('payment.return'), $key);
                } catch (R007ApiException $e) {
                    return redirect()->route('checkout.show', $bookingId)->withInput($request->except('_token'))->with('error', ApiProblem::message($e));
                }

                return $this->handOffToPaystack($request, $payment, $guest, ['flow' => 'booking']);
            });
        }

        return IdempotentSubmit::run($request, "pay:{$bookingId}", function (string $key) use ($bookingId, $request) {
            $booking = $this->loadBooking($bookingId);
            if ($booking instanceof RedirectResponse) {
                return $booking;
            }
            if ($this->secondsLeft($booking) <= 0) {
                return redirect()->route('checkout.show', $bookingId);
            }

            try {
                // Amount is the API's own booking total; the API re-checks it.
                $init = $this->payments->initialize(
                    'bookingId', $bookingId, (string) $booking['total'],
                    (string) ($this->customers->user()['email'] ?? ''), route('payment.return'), $key,
                );
            } catch (R007ApiException $e) {
                return redirect()->route('checkout.show', $bookingId)->with('error', ApiProblem::message($e));
            }

            $this->rememberPayment($request, $init['reference'], ['kind' => 'booking', 'id' => $bookingId]);

            return redirect()->away($init['authorizationUrl']);
        });
    }

    public function release(Request $request, string $bookingId)
    {
        abort_unless(Str::isUuid($bookingId), 404);

        if ($this->guestPath($bookingId)) {
            abort_unless($this->guests->ownsHold($bookingId), 404);

            return IdempotentSubmit::run($request, "release:{$bookingId}", function (string $key) use ($bookingId) {
                $slug = null;
                try {
                    $hold = $this->guestApi->hold($bookingId);
                    $slug = $this->site->slugForFacilityId((string) ($hold['facilityId'] ?? ''));
                    $this->guestApi->releaseHold($bookingId, $key);
                } catch (R007ApiException $e) {
                    return redirect()->route('home')->with('error', ApiProblem::message($e));
                }

                return redirect()->to($slug ? route('book.resources', $slug) : route('home'))->with('status', 'The slot has been released.');
            });
        }

        return IdempotentSubmit::run($request, "release:{$bookingId}", function (string $key) use ($bookingId) {
            try {
                $this->bookings->cancel($this->bookings->get($bookingId), 'Customer released the hold', $key);
            } catch (R007ApiException $e) {
                return redirect()->route('account')->with('error', ApiProblem::message($e));
            }

            return redirect()->route('account')->with('status', 'The slot has been released.');
        });
    }

    /** A hold made by (or opened as) a guest is paid as a guest; account holds stay on the account flow. */
    private function guestPath(string $bookingId): bool
    {
        return ! $this->accountMode() || $this->guests->ownsHold($bookingId);
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function loadHold(string $bookingId)
    {
        try {
            $hold = $this->guestApi->hold($bookingId);
        } catch (R007ApiException $e) {
            if (in_array($e->status, [403, 404], true)) {
                abort(404);
            }
            throw $e;
        }

        $ref = (string) ($hold['orderReference'] ?? '');
        if (in_array($hold['status'] ?? '', ['CONFIRMED', 'COMPLETED', 'RESCHEDULED'], true) && $ref !== '' && $this->guests->token($ref)) {
            return redirect()->route('orders.show', $ref);
        }

        return $hold;
    }

    // ---- helpers ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function slotFacility(string $slug): array
    {
        $facility = $this->site->facility($slug);
        abort_unless($facility && ($facility['flow'] ?? '') === 'slots', 404);

        return $facility;
    }

    /** @param array<string, mixed> $facility */
    private function facilityNotice(array $facility): ?string
    {
        if (empty($facility['id'])) {
            return 'Online booking is temporarily unavailable. Please call or visit reception, or try again shortly.';
        }
        if (! $facility['online_available']) {
            return $facility['notice'] ?? 'Online booking for this facility is temporarily unavailable. Please call or visit reception.';
        }

        return null;
    }

    /** @param array<string, mixed> $facility */
    private function unavailableNotice(R007ApiException $e, array $facility): string
    {
        if ($e->isUnavailable()) {
            return 'Online booking for '.$facility['name'].' is temporarily unavailable. You can still call or visit reception; the rest of the site is working normally.';
        }
        throw $e;
    }

    private function requestedDay(Request $request): CarbonImmutable
    {
        $today = Lagos::today();
        $day = Lagos::dayStart((string) $request->query('date')) ?? $today;
        if ($day < $today) {
            $day = $today;
        }
        $limit = $today->addDays((int) config('r007.booking.horizon_days'));

        return $day > $limit ? $limit : $day;
    }

    /** @return list<CarbonImmutable> */
    private function days(): array
    {
        $today = Lagos::today();

        return array_map(fn ($i) => $today->addDays($i), range(0, min(13, (int) config('r007.booking.horizon_days'))));
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function loadBooking(string $bookingId)
    {
        try {
            $booking = $this->bookings->get($bookingId);
        } catch (R007ApiException $e) {
            if (in_array($e->status, [403, 404], true)) {
                abort(404);
            }
            throw $e;
        }

        if (in_array($booking['status'], ['CONFIRMED', 'COMPLETED', 'RESCHEDULED'], true)) {
            return redirect()->route('account.bookings.show', $bookingId);
        }

        return $booking;
    }

    /** @param array<string, mixed> $booking */
    private function secondsLeft(array $booking): int
    {
        if (! in_array($booking['status'], ['HELD', 'PENDING_PAYMENT'], true)) {
            return 0;
        }
        $expires = isset($booking['holdExpiresAt']) ? CarbonImmutable::parse($booking['holdExpiresAt']) : null;
        if ($expires === null) {
            return (int) config('r007.booking.fallback_hold_seconds');
        }

        return max(0, (int) CarbonImmutable::now()->diffInSeconds($expires, false));
    }

    /** @param array<string, mixed> $context */
    private function rememberPayment(Request $request, string $reference, array $context): void
    {
        $request->session()->put('checkout.pending.'.$reference, $context + ['attempts' => 0]);
    }
}

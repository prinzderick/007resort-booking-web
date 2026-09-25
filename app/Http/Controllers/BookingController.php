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
use App\Support\Money;
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

        if (! $this->accountMode()) {
            // Guest: no login. The slot is held when they continue to payment (the API creates the hold together
            // with the guest's contact details), so remember the choice and ask for name, email and phone.
            $this->guests->putDraft('booking', ['slug' => $slug, 'resourceId' => $resourceId, 'start' => $start, 'end' => $end, 'quantity' => (int) ($data['quantity'] ?? 1)]);

            return redirect()->route('checkout.booking');
        }

        $user = $this->customers->user() ?? [];

        return IdempotentSubmit::run($request, "hold:{$resourceId}", function (string $key) use ($resourceId, $start, $end, $data, $user, $back) {
            try {
                $booking = $this->bookings->hold($resourceId, $start, $end, (int) ($data['quantity'] ?? 1), array_filter([
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'phone' => $user['phone'] ?? null,
                ]), $key);
            } catch (R007ApiException $e) {
                // 409 slot_unavailable: back to a freshly loaded grid.
                return redirect()->to($back)->with('error', ApiProblem::message($e));
            }

            return redirect()->route('checkout.show', $booking['id']);
        });
    }

    /** Guest "Your details" step for a slot the visitor has picked (nothing is held yet). */
    public function guestCheckout(Request $request)
    {
        if ($this->accountMode()) {
            return redirect()->route('sports');
        }
        $draft = $this->guestDraft();
        if ($draft instanceof RedirectResponse) {
            return $draft;
        }

        return view('booking.checkout-guest', $draft + ['prefill' => $this->prefill($request)]);
    }

    public function guestPay(Request $request)
    {
        $draft = $this->guestDraft();
        if ($draft instanceof RedirectResponse) {
            return $draft;
        }
        $guest = GuestDetails::validate($request);
        $back = route('book.slots', [$draft['slug'], $draft['resourceId'], 'date' => Lagos::parse($draft['start'])?->format('Y-m-d')]);

        return IdempotentSubmit::run($request, 'guest-book:'.$draft['resourceId'], function (string $key) use ($draft, $guest, $request, $back) {
            try {
                $start = $this->guestApi->startBooking($draft['resourceId'], $draft['start'], $draft['end'], (int) $draft['quantity'], $guest, $key);
            } catch (R007ApiException $e) {
                if ($e->is('slot_unavailable')) {
                    return redirect()->to($back)->with('error', ApiProblem::message($e));
                }

                return redirect()->route('checkout.booking')->withInput($request->except('_token'))->with('error', ApiProblem::message($e));
            }

            return $this->beginGuestPayment($request, $start, $guest, $key, 'booking');
        });
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function guestDraft()
    {
        $d = $this->guests->draft('booking');
        if (! $d) {
            return redirect()->route('sports')->with('notice', 'Choose a court or time first.');
        }
        $facility = $this->site->facility($d['slug']);
        $resource = $facility ? $this->safeResource($facility, $d['resourceId']) : null;
        $start = Lagos::parse($d['start']);
        if (! $resource || ! $start || $start->isPast()) {
            $this->guests->forgetDraft('booking');

            return redirect()->route('sports')->with('notice', 'That time is no longer available. Please choose again.');
        }
        $qty = ($resource['mode'] ?? '') === 'INDIVIDUAL_CAPACITY' ? (int) $d['quantity'] : 1;

        return $d + ['resource' => $resource, 'facility' => $facility, 'total' => Money::fromMinor((int) Money::minor($resource['price']) * $qty), 'qty' => $qty];
    }

    /** @param array<string, mixed> $facility @return array<string, mixed>|null */
    private function safeResource(array $facility, string $resourceId): ?array
    {
        try {
            return $this->bookings->resource($facility['ids'] ?? [$facility['id']], $resourceId);
        } catch (R007ApiException $e) {
            if ($e->isUnavailable()) {
                return null;
            }
            throw $e;
        }
    }

    public function checkout(Request $request, string $bookingId)
    {
        abort_unless(Str::isUuid($bookingId), 404);
        if (! $this->accountMode()) {
            // Guests never have a numbered checkout link: their order lives at /booking/{reference}.
            return redirect()->route('sports')->with('notice', 'That checkout link is no longer valid. Choose your time again.');
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
        abort_unless($this->accountMode(), 404);

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
        abort_unless($this->accountMode(), 404);

        return IdempotentSubmit::run($request, "release:{$bookingId}", function (string $key) use ($bookingId) {
            try {
                $this->bookings->cancel($this->bookings->get($bookingId), 'Customer released the hold', $key);
            } catch (R007ApiException $e) {
                return redirect()->route('account')->with('error', ApiProblem::message($e));
            }

            return redirect()->route('account')->with('status', 'The slot has been released.');
        });
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

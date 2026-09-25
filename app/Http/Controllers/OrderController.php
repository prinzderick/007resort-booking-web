<?php

namespace App\Http\Controllers;

use App\Services\Guest\GuestCheckoutApi;
use App\Services\Guest\GuestSession;
use App\Services\Online\CustomerService;
use App\Services\Online\SiteService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\IdempotentSubmit;
use App\Support\Lagos;
use App\Support\Mask;
use App\Support\Phone;
use App\Support\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

/**
 * Guest order pages: confirmation, tickets/QR, manage, find-my-booking. Access is by an order access
 * token that lives only in this browser's session. The `?t=` link is exchanged for the session on first
 * visit and the browser is redirected to the clean URL straight away.
 */
class OrderController extends Controller
{
    private const REF = '/^[A-Za-z0-9\-]{4,40}$/';

    private const TOKEN = '/^[A-Za-z0-9_\-.~]{16,512}$/';

    public function __construct(
        private readonly GuestCheckoutApi $api,
        private readonly GuestSession $guests,
        private readonly SiteService $site,
        private readonly CustomerService $customers,
    ) {}

    // ---- find my booking -------------------------------------------------

    public function find(Request $request)
    {
        return view('orders.find', [
            'recent' => $this->guests->references(),
            'turnstileKey' => Turnstile::siteKey(),
            'reference' => (string) $request->query('reference', ''),
        ]);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:60'],
            'contact' => ['required', 'string', 'max:190'],
        ], [
            'reference.required' => 'Enter the booking reference from your confirmation.',
            'contact.required' => 'Enter the email or phone number you booked with.',
        ]);

        $reference = strtoupper(preg_replace('/\s+/', '', $data['reference']) ?? '');
        $contact = trim($data['contact']);
        $isEmail = str_contains($contact, '@');
        $contact = $isEmail ? strtolower($contact) : Phone::normalize($contact);

        // Same message for every kind of miss: never reveal which part was wrong or whether a booking exists.
        $miss = 'We could not find a booking with those details. Check the reference and the email or phone number you used when you booked.';
        if (preg_match(self::REF, $reference) !== 1 || $contact === null || ($isEmail && ! filter_var($contact, FILTER_VALIDATE_EMAIL))) {
            return back()->withInput($request->only('reference', 'contact'))->withErrors(['form' => $miss]);
        }
        if (! Turnstile::passes($request)) {
            return back()->withInput($request->only('reference', 'contact'))->withErrors(['form' => 'Please complete the security check and try again.']);
        }

        try {
            $found = $this->api->lookup($reference, $contact);
        } catch (R007ApiException $e) {
            $msg = match (true) {
                $e->status === 429 => 'Too many tries. Please wait a minute and try again.',
                $e->isUnavailable() => 'Booking look-up is temporarily unavailable. Please try again shortly or contact us.',
                default => $miss,
            };

            return back()->withInput($request->only('reference', 'contact'))->withErrors(['form' => $msg]);
        }
        if ($found['accessToken'] === '') {
            return back()->withInput($request->only('reference', 'contact'))->withErrors(['form' => $miss]);
        }

        $this->guests->remember($found['reference'], $found['accessToken']);

        return redirect()->route('orders.show', $found['reference']);
    }

    // ---- the order page --------------------------------------------------

    public function show(Request $request, string $reference)
    {
        $reference = $this->ref($reference);

        if ($request->query->has('t')) {
            return $this->exchange($request, $reference);
        }

        $loaded = $this->load($reference);
        if ($loaded instanceof Response) {
            return $loaded;
        }
        [$order, $token] = $loaded;

        $status = (string) $order['status'];
        $tickets = array_values((array) $order['tickets']);
        $secondsLeft = 0;
        if ($status === 'PENDING_PAYMENT' && ! empty($order['holdExpiresAt'])) {
            $secondsLeft = max(0, (int) CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($order['holdExpiresAt']), false));
        }
        $slug = ! empty($order['facilityId']) ? $this->site->slugForFacilityId((string) $order['facilityId']) : null;

        return view('orders.show', [
            'order' => $order,
            'tickets' => $tickets,
            'state' => match (true) {
                $status === 'PAID' && $tickets !== [] => 'paid',
                $status === 'PAID' => 'issuing',
                default => strtolower($status),
            },
            'secondsLeft' => $secondsLeft,
            'slug' => $slug,
            'manageUrl' => route('orders.show', ['reference' => $reference, 't' => $token]),
            'justPaid' => (bool) session('just_paid'),
            'email' => (string) ($order['guest']['email'] ?? ''),
            'emailMasked' => Mask::email($order['guest']['email'] ?? ''),
            'delivery' => [
                'sent' => (bool) ($order['delivery']['emailSent'] ?? false),
                'available' => (bool) ($order['delivery']['available'] ?? false),
            ],
            'canCalendar' => (($order['kind'] ?? '') === 'booking' && ! empty($order['start'])) || ! empty($order['visitDate']),
            'signedIn' => $this->customers->check(),
        ]);
    }

    /** ?t=<token>: move the token into this browser's session, then drop it from the URL. */
    private function exchange(Request $request, string $reference)
    {
        $t = $request->query('t');
        if (! is_string($t) || preg_match(self::TOKEN, $t) !== 1) {
            return redirect()->route('find.show', ['reference' => $reference]);
        }
        $this->guests->remember($reference, $t);

        return redirect()->route('orders.show', $reference);
    }

    public function pay(Request $request, string $reference)
    {
        $reference = $this->ref($reference);
        $token = $this->guests->token($reference);
        abort_if($token === null, 404);

        return IdempotentSubmit::run($request, "order-pay:{$reference}", function (string $key) use ($reference, $token, $request) {
            try {
                $payment = $this->api->retryPayment($reference, $token, route('payment.return'), $key);
            } catch (R007ApiException $e) {
                return redirect()->route('orders.show', $reference)->with('error', ApiProblem::message($e));
            }
            abort_if($payment->authorizationUrl === '', 502);
            $request->session()->put('checkout.pending.'.$payment->paymentReference, ['kind' => 'guest', 'order' => $reference, 'attempts' => 0, 'flow' => 'retry']);

            return redirect()->away($payment->authorizationUrl);
        });
    }

    public function resend(Request $request, string $reference)
    {
        $reference = $this->ref($reference);
        $token = $this->guests->token($reference);
        abort_if($token === null, 404);

        return IdempotentSubmit::run($request, "order-resend:{$reference}", function (string $key) use ($reference, $token) {
            try {
                $r = $this->api->resend($reference, $token, $key);
            } catch (R007ApiException $e) {
                return redirect()->route('orders.show', $reference)->with('error', ApiProblem::message($e));
            }
            // Truthful: only say "sent" when the API says it was.
            $msg = ! empty($r['emailSent'])
                ? 'We have sent your ticket again. Check your inbox and spam folder.'
                : 'We cannot send emails right now. Please keep this page or save your ticket using the buttons below.';

            return redirect()->route('orders.show', $reference)->with(! empty($r['emailSent']) ? 'status' : 'notice', $msg);
        });
    }

    public function cancel(Request $request, string $reference)
    {
        $reference = $this->ref($reference);
        $token = $this->guests->token($reference);
        abort_if($token === null, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return IdempotentSubmit::run($request, "order-cancel:{$reference}", function (string $key) use ($reference, $token, $data) {
            try {
                $this->api->cancel($reference, $token, ($data['reason'] ?? null) ?: 'Cancelled by guest', $key);
            } catch (R007ApiException $e) {
                return redirect()->route('orders.show', $reference)->with('error', ApiProblem::message($e));
            }

            return redirect()->route('orders.show', $reference)->with('status', 'Your booking has been cancelled. Any refund follows the cancellation policy below.');
        });
    }

    /** After paying: "keep everything in one place" with the same details. Only asks for a password. */
    public function account(Request $request, string $reference)
    {
        $reference = $this->ref($reference);
        $loaded = $this->load($reference);
        if ($loaded instanceof Response) {
            return $loaded;
        }
        [$order] = $loaded;
        $data = $request->validate(['password' => ['required', 'string', Password::min(10)]], [
            'password.required' => 'Choose a password to save your bookings.',
        ]);
        $g = (array) $order['guest'];

        try {
            $this->customers->register([
                'name' => (string) ($g['name'] ?? ''), 'email' => (string) ($g['email'] ?? ''), 'phone' => (string) ($g['phone'] ?? ''), 'password' => $data['password'],
            ], 'guest-account:'.hash('sha256', $reference.$request->input('_submission', '')));
        } catch (R007ApiException $e) {
            $msg = $e->status === 422 || $e->status === 409
                ? 'There is already an account with this email. You can sign in to see all your bookings.'
                : ApiProblem::message($e);

            return redirect()->route('orders.show', $reference)->withErrors(['password' => $msg], 'account');
        }

        return redirect()->route('verify', ['email' => $g['email']])->with('notice', 'Almost done. Enter the code we sent to confirm your email, and your bookings will be in one place.');
    }

    /** Add-to-calendar file for bookings and pool visits. */
    public function ics(Request $request, string $reference)
    {
        $reference = $this->ref($reference);
        $loaded = $this->load($reference);
        if ($loaded instanceof Response) {
            return $loaded;
        }
        [$order] = $loaded;
        abort_unless(($order['status'] ?? '') === 'PAID', 404);

        $esc = fn (string $s) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);
        $host = parse_url(url('/'), PHP_URL_HOST);
        $contact = $this->site->site()['contact'] ?? [];
        $summary = $esc(($order['title'] ?? 'Your visit').' at 007 Resort & Spa');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//007 Resort & Spa//Booking//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:'.strtolower($reference).'@'.$host, 'DTSTAMP:'.CarbonImmutable::now()->utc()->format('Ymd\THis\Z')];
        if (! empty($order['start']) && ! empty($order['end']) && $order['kind'] === 'booking') {
            $lines[] = 'DTSTART:'.CarbonImmutable::parse($order['start'])->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.CarbonImmutable::parse($order['end'])->utc()->format('Ymd\THis\Z');
        } elseif (! empty($order['visitDate'])) {
            $day = Lagos::dayStart(substr((string) $order['visitDate'], 0, 10)) ?? abort(404);
            $lines[] = 'DTSTART;VALUE=DATE:'.$day->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$day->addDay()->format('Ymd');
        } else {
            abort(404);
        }
        $lines[] = 'SUMMARY:'.$summary;
        $lines[] = 'DESCRIPTION:'.$esc('Booking reference '.$reference.'. Show your QR ticket at the entrance.');
        $lines[] = 'LOCATION:'.$esc((string) ($contact['address'] ?? '007 Resort & Spa'));
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="007-resort-'.strtolower($reference).'.ics"',
        ]);
    }

    // ---- helpers ---------------------------------------------------------

    private function ref(string $reference): string
    {
        abort_unless(preg_match(self::REF, $reference) === 1, 404);

        return $reference;
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}|Response
     */
    private function load(string $reference)
    {
        $token = $this->guests->token($reference);
        if ($token === null) {
            return response()->view('orders.locked', ['reference' => $reference], 403);
        }
        try {
            return [$this->api->order($reference, $token), $token];
        } catch (R007ApiException $e) {
            if (in_array($e->status, [401, 403, 404], true)) {
                $this->guests->forget($reference);

                return response()->view('orders.locked', ['reference' => $reference], 403);
            }
            if ($e->isUnavailable()) {
                return response()->view('errors.unavailable', [], 503);
            }
            throw $e;
        }
    }
}

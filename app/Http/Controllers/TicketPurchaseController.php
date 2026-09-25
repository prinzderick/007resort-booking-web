<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesGuestCheckout;
use App\Services\Guest\GuestCheckoutApi;
use App\Services\Guest\GuestSession;
use App\Services\Online\ContentService;
use App\Services\Online\CustomerService;
use App\Services\Online\PaymentService;
use App\Services\Online\SiteService;
use App\Services\Online\TicketService;
use App\Services\R007Api\R007ApiException;
use App\Support\ApiProblem;
use App\Support\GuestDetails;
use App\Support\IdempotentSubmit;
use App\Support\Lagos;
use App\Support\Money;
use Illuminate\Http\Request;

/** Pool day tickets: adult/child counts -> unpaid order -> Paystack -> individual QR tickets. */
class TicketPurchaseController extends Controller
{
    use HandlesGuestCheckout;

    public function __construct(
        private readonly SiteService $site,
        private readonly TicketService $tickets,
        private readonly PaymentService $payments,
        private readonly CustomerService $customers,
        private readonly ContentService $content,
        private readonly GuestCheckoutApi $guestApi,
        private readonly GuestSession $guests,
    ) {}

    public function form()
    {
        $facility = $this->facility();
        $products = [];
        $notice = null;

        if (empty($facility['id']) || ! $facility['online_available']) {
            $notice = $facility['notice'] ?? 'Online ticket sales are temporarily unavailable. You can still buy tickets at the pool reception.';
        } else {
            try {
                $products = $this->tickets->ticketProducts($facility['id']);
            } catch (R007ApiException $e) {
                if (! $e->isUnavailable()) {
                    throw $e;
                }
                $notice = 'Online ticket sales are temporarily unavailable. You can still buy tickets at the pool reception.';
            }
        }

        $today = Lagos::today();

        return view('tickets.buy', [
            'facility' => $facility,
            'products' => $products,
            'notice' => $notice,
            'dates' => array_map(fn ($i) => $today->addDays($i), range(0, min(13, (int) config('r007.booking.horizon_days')))),
            'max' => (int) config('r007.booking.max_tickets_per_order'),
            'cms' => $this->content->page('pool'),
            'strip' => $this->content->strip('pool', 8),
        ]);
    }

    public function order(Request $request)
    {
        $facility = $this->facility();
        $max = (int) config('r007.booking.max_tickets_per_order');
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:'.$max],
        ]);

        $lines = [];
        foreach ($data['qty'] as $productId => $qty) {
            if ((int) $qty > 0 && preg_match('/^[0-9a-f-]{36}$/i', (string) $productId)) {
                $lines[] = ['productId' => (string) $productId, 'quantity' => (int) $qty];
            }
        }
        $count = array_sum(array_column($lines, 'quantity'));
        if ($count < 1 || $count > $max) {
            return back()->withInput()->withErrors(['qty' => "Choose between 1 and {$max} tickets."]);
        }
        $visit = Lagos::dayStart($data['date']);
        if ($visit === null || $visit < Lagos::today() || $visit > Lagos::today()->addDays((int) config('r007.booking.horizon_days'))) {
            return back()->withInput()->withErrors(['date' => 'Please choose a valid visit date.']);
        }

        if (! $this->accountMode()) {
            // Guest: remember the choice and go to the "Your details" step (no login, no account).
            $this->guests->putDraft('pool', ['date' => $data['date'], 'lines' => $lines]);

            return redirect()->route('checkout.pool');
        }

        $user = $this->customers->user() ?? [];

        return IdempotentSubmit::run($request, 'pool-order', function (string $key) use ($facility, $data, $lines, $user, $request) {
            try {
                $order = $this->tickets->createOrder($facility['id'], $data['date'], $lines, array_filter([
                    'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null, 'phone' => $user['phone'] ?? null,
                ]), $key);
                $init = $this->payments->initialize('orderIds', [$order['id']], (string) $order['total'], (string) ($user['email'] ?? ''), route('payment.return'), $key.':pay');
            } catch (R007ApiException $e) {
                return redirect()->route('pool')->with('error', ApiProblem::message($e));
            }

            $request->session()->put('checkout.pending.'.$init['reference'], ['kind' => 'tickets', 'id' => $order['id'], 'attempts' => 0]);

            return redirect()->away($init['authorizationUrl']);
        });
    }

    /** "Your details" step for pool day passes. */
    public function checkout(Request $request)
    {
        if ($this->accountMode()) {
            return redirect()->route('pool');
        }
        $summary = $this->draftSummary();
        if ($summary === null) {
            return redirect()->route('pool')->with('notice', 'Choose your date and tickets first.');
        }

        return view('tickets.checkout', $summary + ['prefill' => $this->prefill($request)]);
    }

    public function pay(Request $request)
    {
        if ($this->accountMode()) {
            return redirect()->route('pool');
        }
        $summary = $this->draftSummary();
        if ($summary === null) {
            return redirect()->route('pool')->with('notice', 'Choose your date and tickets first.');
        }
        $guest = GuestDetails::validate($request);
        $facility = $this->facility();

        return IdempotentSubmit::run($request, 'pool-pay', function (string $key) use ($facility, $summary, $guest, $request) {
            try {
                $start = $this->guestApi->startTickets($facility['id'], $summary['date'], $summary['lines'], $guest, $key);
            } catch (R007ApiException $e) {
                return redirect()->route('checkout.pool')->withInput($request->except('_token'))->with('error', ApiProblem::message($e));
            }

            return $this->beginGuestPayment($request, $start, $guest, $key, 'tickets');
        });
    }

    /**
     * The draft (date + lines) priced with the API's current catalogue, for display only: the API prices the order.
     *
     * @return array{date: string, lines: list<array{productId: string, quantity: int}>, rows: list<array{name: string, qty: int, unit: string, sum: string}>, total: string}|null
     */
    private function draftSummary(): ?array
    {
        $draft = $this->guests->draft('pool');
        $facility = $this->facility();
        if (! $draft || empty($facility['id'])) {
            return null;
        }
        $visit = Lagos::dayStart($draft['date']);
        if ($visit === null || $visit < Lagos::today()) {
            $this->guests->forgetDraft('pool');

            return null;
        }
        try {
            $products = collect($this->tickets->ticketProducts($facility['id']))->keyBy('id');
        } catch (R007ApiException $e) {
            if ($e->isUnavailable()) {
                return null;
            }
            throw $e;
        }
        $rows = [];
        $lines = [];
        $kobo = 0;
        foreach ($draft['lines'] as $l) {
            $p = $products->get($l['productId']);
            if (! $p) {
                continue;
            }
            $unit = (int) Money::minor($p['price']);
            $kobo += $unit * $l['quantity'];
            $lines[] = $l;
            $rows[] = ['name' => $p['name'], 'qty' => $l['quantity'], 'unit' => Money::format($p['price']), 'sum' => Money::format(Money::fromMinor($unit * $l['quantity']))];
        }
        if ($lines === []) {
            return null;
        }

        return ['date' => $draft['date'], 'visit' => $visit, 'lines' => $lines, 'rows' => $rows, 'total' => Money::fromMinor($kobo)];
    }

    /** @return array<string, mixed> */
    private function facility(): array
    {
        $facility = $this->site->facility('pool');
        abort_unless($facility !== null, 404);

        return $facility;
    }
}

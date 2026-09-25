<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Cms\CmsClient;
use App\Services\Guest\GuestPayment;
use App\Services\Guest\GuestSession;
use App\Services\Online\CustomerService;
use App\Support\GuestDetails;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared by the booking / pool / membership checkout controllers so guests and signed-in customers
 * go through the same components. "Account mode" = a signed-in customer with a complete profile: the
 * details card is skipped and the customer-token flow runs exactly as before. Everyone else pays
 * as a guest: name, email, phone, no password, no account.
 */
trait HandlesGuestCheckout
{
    protected function accountMode(): bool
    {
        $customers = app(CustomerService::class);

        return $customers->check() && GuestDetails::completeProfile($customers->user()) !== null;
    }

    /** @return array{name: string, email: string, phone: string} values to prefill the card (old input wins). */
    protected function prefill(Request $request): array
    {
        $profile = app(CustomerService::class)->user() ?? [];
        $saved = (array) $request->session()->get('guest.details', []);
        $pick = fn (string $old, string $key) => (string) $request->old($old, $saved[$key] ?? $profile[$key] ?? '');

        return [
            'name' => $pick('guest_name', 'name'),
            'email' => $pick('guest_email', 'email'),
            'phone' => $request->old('guest_phone') !== null ? (string) $request->old('guest_phone') : Phone::display($saved['phone'] ?? $profile['phone'] ?? null),
            'marketing' => (bool) $request->old('guest_marketing', false),
        ];
    }

    /**
     * Remember the order in this browser only, note where the Paystack return should land, opt in to news
     * if (and only if) the box was ticked, then hand off to Paystack.
     *
     * @param  array<string, mixed>  $guest
     * @param  array<string, mixed>  $context  e.g. ['flow' => 'booking', 'retry' => url]
     */
    protected function handOffToPaystack(Request $request, GuestPayment $payment, array $guest, array $context = []): RedirectResponse
    {
        abort_if($payment->authorizationUrl === '' || $payment->orderReference === '', 502);

        $guests = app(GuestSession::class);
        if ($payment->accessToken) {
            $guests->remember($payment->orderReference, $payment->accessToken);
        }
        $request->session()->put('guest.details', ['name' => $guest['name'], 'email' => $guest['email'], 'phone' => $guest['phone']]);
        $request->session()->put('checkout.pending.'.$payment->paymentReference, ['kind' => 'guest', 'order' => $payment->orderReference, 'attempts' => 0] + $context);

        if (! empty($guest['marketingConsent'])) {
            $this->subscribeToNews($request, $guest['email']);
        }

        return redirect()->away($payment->authorizationUrl);
    }

    /** Uses the existing double opt-in newsletter flow (the CMS sends the confirmation email). */
    private function subscribeToNews(Request $request, string $email): void
    {
        try {
            app(CmsClient::class)->subscribe($email, null, 'checkout', true, 'I agree to receive emails from 007 Resort & Spa.', '', $request->ip());
        } catch (\Throwable) {
            // Never let a marketing sign-up problem get in the way of a payment.
        }
    }
}

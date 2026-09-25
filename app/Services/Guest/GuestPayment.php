<?php

namespace App\Services\Guest;

/** Result of initialising a Paystack payment for a guest order. */
final class GuestPayment
{
    public function __construct(
        public readonly string $orderReference,
        public readonly string $paymentReference,
        public readonly string $authorizationUrl,
    ) {}
}

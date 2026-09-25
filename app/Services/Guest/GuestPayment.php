<?php

namespace App\Services\Guest;

/** Result of starting (or retrying) a guest payment. The access token is a secret: never log or render it. */
final class GuestPayment
{
    public function __construct(
        public readonly string $orderReference,
        public readonly ?string $accessToken,
        public readonly string $paymentReference,
        public readonly string $authorizationUrl,
    ) {}

    /** @param array<string, mixed> $body */
    public static function fromApi(array $body, ?string $fallbackReference = null, ?string $fallbackToken = null): self
    {
        $payment = (array) ($body['payment'] ?? []);

        return new self(
            (string) ($body['reference'] ?? $fallbackReference ?? ''),
            isset($body['accessToken']) ? (string) $body['accessToken'] : $fallbackToken,
            (string) ($payment['reference'] ?? $body['paymentReference'] ?? ''),
            (string) ($payment['authorizationUrl'] ?? $body['authorizationUrl'] ?? ''),
        );
    }
}

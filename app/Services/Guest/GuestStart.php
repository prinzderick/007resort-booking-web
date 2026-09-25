<?php

namespace App\Services\Guest;

/**
 * A freshly created guest purchase (hold, ticket order or membership) BEFORE payment starts. The access
 * token is a secret: the caller stores it in the visitor's session straight away so the order can never be
 * lost even if payment start fails.
 */
final class GuestStart
{
    public function __construct(
        public readonly string $reference,
        public readonly string $accessToken,
        /** @var 'bookingId'|'orderIds'|'membershipId' */
        public readonly string $subjectKey,
        public readonly string $subjectId,
        public readonly string $amount,
    ) {}
}

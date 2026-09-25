<?php

namespace App\Services\Social;

use RuntimeException;

/** A social sign-in that cannot continue. `reason` is a stable, PII-free code; the message is customer-safe. */
class SocialAuthException extends RuntimeException
{
    public const DENIED = 'denied';

    public const BAD_STATE = 'bad_state';

    public const PROVIDER_ERROR = 'provider_error';

    public const UNAVAILABLE = 'unavailable';

    public const NO_IDENTITY = 'no_identity';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function denied(): self
    {
        return new self(self::DENIED, 'You cancelled the sign-in, so nothing was changed. You can try again or use your email and password.');
    }

    public static function badState(): self
    {
        return new self(self::BAD_STATE, 'That sign-in link has expired or was already used. Please start again.');
    }

    public static function providerError(): self
    {
        return new self(self::PROVIDER_ERROR, 'We could not complete the sign-in with that provider. Please try again, or use your email and password.');
    }

    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE, 'Sign-in with that provider is not available right now. Please use your email and password.');
    }

    public static function noIdentity(): self
    {
        return new self(self::NO_IDENTITY, 'Your sign-in session expired. Please start again.');
    }
}

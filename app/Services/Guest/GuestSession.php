<?php

namespace App\Services\Guest;

use Illuminate\Contracts\Session\Session;

/**
 * What this browser is allowed to see, kept ONLY in the visitor's own session (encrypted at rest,
 * cookie holds just the session id). Order access tokens never go in logs, URLs we generate, or the DOM.
 */
class GuestSession
{
    private const TOKENS = 'guest.tokens';

    private const HOLDS = 'guest.holds';

    private const DRAFT = 'guest.draft';

    public function __construct(private readonly Session $session) {}

    public function remember(string $reference, string $token): void
    {
        $tokens = (array) $this->session->get(self::TOKENS, []);
        unset($tokens[$reference]);
        $tokens[$reference] = $token; // newest last
        $max = (int) config('r007.checkout.max_orders_in_session', 20);
        $this->session->put(self::TOKENS, array_slice($tokens, -$max, null, true));
    }

    public function token(string $reference): ?string
    {
        $t = ((array) $this->session->get(self::TOKENS, []))[$reference] ?? null;

        return is_string($t) && $t !== '' ? $t : null;
    }

    /** Constant-time comparison of a presented token against the one this browser holds. */
    public function matches(string $reference, string $presented): bool
    {
        $held = $this->token($reference);

        return $held !== null && hash_equals($held, $presented);
    }

    public function forget(string $reference): void
    {
        $tokens = (array) $this->session->get(self::TOKENS, []);
        unset($tokens[$reference]);
        $this->session->put(self::TOKENS, $tokens);
    }

    /** @return list<string> references this browser can open, newest first */
    public function references(): array
    {
        return array_reverse(array_keys((array) $this->session->get(self::TOKENS, [])));
    }

    public function rememberHold(string $bookingId): void
    {
        $holds = array_slice(array_unique([...(array) $this->session->get(self::HOLDS, []), $bookingId]), -10);
        $this->session->put(self::HOLDS, array_values($holds));
    }

    public function ownsHold(string $bookingId): bool
    {
        return in_array($bookingId, (array) $this->session->get(self::HOLDS, []), true);
    }

    /** Ticket / membership choices made before the details step. @param array<string, mixed> $draft */
    public function putDraft(string $kind, array $draft): void
    {
        $this->session->put(self::DRAFT.'.'.$kind, $draft);
    }

    /** @return array<string, mixed>|null */
    public function draft(string $kind): ?array
    {
        $d = $this->session->get(self::DRAFT.'.'.$kind);

        return is_array($d) ? $d : null;
    }

    public function forgetDraft(string $kind): void
    {
        $this->session->forget(self::DRAFT.'.'.$kind);
    }
}

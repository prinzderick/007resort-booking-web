<?php

namespace App\Services\Social;

/**
 * What a provider told us about the person, exactly as reported. No access/refresh tokens live here: they are
 * dropped as soon as the profile has been read. `emailVerified` is the provider's own claim (false when the
 * provider does not report it); this site never upgrades it.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $givenName,
        public ?string $familyName,
        public ?string $avatarUrl,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['provider'], (string) $a['providerUserId'], $a['email'] ?? null, (bool) ($a['emailVerified'] ?? false),
            $a['name'] ?? null, $a['givenName'] ?? null, $a['familyName'] ?? null, $a['avatarUrl'] ?? null,
        );
    }
}

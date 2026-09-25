<?php

namespace App\Services\Social;

use App\Services\R007Api\R007ApiException;

/**
 * The social-login surface of the API (contract: 007resort-api docs/CUSTOMER_SOCIAL_LOGIN.md).
 * Implementations: HttpSocialAuthApi (real API or the in-process Mock API). Tests can bind a fake.
 */
interface SocialAuthApi
{
    /**
     * Provider ids the API has enabled (`GET public/customers/social/providers`).
     *
     * @return list<string>
     */
    public function providers(): array;

    /**
     * `POST public/customers/social/login` (service token). 200/201 session payload, or R007ApiException
     * (422 terms_not_accepted, 409 account_link_requires_confirmation, ...).
     *
     * @param  array{termsAccepted: bool, marketingConsent?: bool, phone?: ?string, clientIp?: ?string, userAgent?: ?string}  $context
     * @return array<string, mixed>
     */
    public function login(SocialIdentity $identity, array $context): array;

    /**
     * `POST public/customers/social/link/confirm`: finish a pending link with the code emailed to the account owner.
     *
     * @return array<string, mixed>
     */
    public function confirmLink(string $email, string $code): array;

    /**
     * `POST customer/me/social/link`: connect another provider to the signed-in customer.
     *
     * @return array<string, mixed>
     */
    public function link(SocialIdentity $identity, string $customerToken): array;

    /** @return array{items: list<array<string, mixed>>, hasPassword: bool, canUnlink: bool} */
    public function identities(): array;

    public function unlink(string $identityId): void;

    public function setPassword(string $password, ?string $currentPassword): void;

    /** @param  array{name?: string, phone?: ?string}  $fields */
    public function updateProfile(array $fields): array;

    public function requestEmail(string $email): void;

    /** @return array<string, mixed> the updated customer */
    public function verifyEmail(string $code): array;
}

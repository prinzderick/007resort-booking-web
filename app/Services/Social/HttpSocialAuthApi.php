<?php

namespace App\Services\Social;

use App\Services\R007Api\R007ApiClient;

class HttpSocialAuthApi implements SocialAuthApi
{
    public function __construct(private readonly R007ApiClient $api) {}

    public function providers(): array
    {
        $body = $this->api->asService(fn (R007ApiClient $c) => $c->get('public/customers/social/providers'));
        $out = [];
        foreach ((array) ($body['providers'] ?? []) as $p) {
            if (is_array($p) && ($p['enabled'] ?? false) === true && is_string($p['id'] ?? null)) {
                $out[] = $p['id'];
            }
        }

        return $out;
    }

    public function login(SocialIdentity $identity, array $context): array
    {
        return $this->api->asService(fn (R007ApiClient $c) => $c->post('public/customers/social/login', $this->claims($identity) + array_filter([
            'termsAccepted' => (bool) $context['termsAccepted'],
            'marketingConsent' => (bool) ($context['marketingConsent'] ?? false),
            'phone' => $context['phone'] ?? null,
            'clientIp' => $context['clientIp'] ?? null,
            'userAgent' => isset($context['userAgent']) ? mb_substr((string) $context['userAgent'], 0, 250) : null,
        ], fn ($v) => $v !== null)));
    }

    public function confirmLink(string $email, string $code): array
    {
        return $this->api->asService(fn (R007ApiClient $c) => $c->post('public/customers/social/link/confirm', ['email' => $email, 'code' => $code]));
    }

    public function link(SocialIdentity $identity, string $customerToken): array
    {
        return $this->api->asService(fn (R007ApiClient $c) => $c->post(
            'customer/me/social/link',
            $this->claims($identity),
            null,
            ['X-Customer-Token' => $customerToken],
        ));
    }

    public function identities(): array
    {
        $b = $this->api->get('customer/me/identities');

        return [
            'items' => array_values((array) ($b['items'] ?? [])),
            'hasPassword' => (bool) ($b['hasPassword'] ?? false),
            'canUnlink' => (bool) ($b['canUnlink'] ?? false),
        ];
    }

    public function unlink(string $identityId): void
    {
        $this->api->delete('customer/me/identities/'.rawurlencode($identityId));
    }

    public function setPassword(string $password, ?string $currentPassword): void
    {
        $this->api->post('customer/me/password', array_filter(['password' => $password, 'currentPassword' => $currentPassword], fn ($v) => $v !== null));
    }

    public function updateProfile(array $fields): array
    {
        return $this->api->patch('customer/me', $fields);
    }

    public function requestEmail(string $email): void
    {
        $this->api->post('customer/me/email', ['email' => $email]);
    }

    public function verifyEmail(string $code): array
    {
        return $this->api->post('customer/me/email/verify', ['code' => $code]);
    }

    /**
     * Claims exactly as the provider reported them. `emailVerified` is never upgraded here; the API decides
     * whether it trusts the claim for that provider.
     *
     * @return array<string, mixed>
     */
    private function claims(SocialIdentity $i): array
    {
        return array_filter([
            'provider' => $i->provider,
            'providerUserId' => $i->providerUserId,
            'email' => $i->email,
            'emailVerified' => $i->email !== null && $i->emailVerified,
            'givenName' => $i->givenName,
            'familyName' => $i->familyName,
            'name' => $i->name,
            'avatarUrl' => $i->avatarUrl,
        ], fn ($v) => $v !== null);
    }
}

<?php

namespace App\Services\Social;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * The OAuth authorization-code dance with the provider.
 *
 * - `state` is a per-attempt random value kept in the (server-side) session and consumed on callback
 *   (Socialite `pull`s it), so a forged, missing, wrong or REPLAYED callback is rejected.
 * - Google additionally uses PKCE.
 * - The redirect URI is built from config APP_URL only, never from Host / X-Forwarded-* headers.
 * - Only the profile is read; the provider access token is discarded and never stored or logged.
 *
 * In fake mode (dev/test only) the "provider" is a local screen (App\Http\Controllers\FakeProviderController).
 */
class SocialGateway
{
    public const FAKE_STATE_KEY = 'social.fake_state';

    public function __construct(private readonly SocialProviders $providers) {}

    public function callbackUrl(string $provider): string
    {
        return rtrim((string) config('app.url'), '/').'/auth/'.$provider.'/callback';
    }

    public function redirect(string $provider, Request $request): RedirectResponse
    {
        if ($this->providers->fakeEnabled()) {
            $state = Str::random(40);
            $request->session()->put(self::FAKE_STATE_KEY, $state);

            return redirect()->to(rtrim((string) config('app.url'), '/').'/auth/fake/'.$provider.'/authorize?'.http_build_query(['state' => $state]));
        }

        return $this->driver($provider)->redirect();
    }

    /** @throws SocialAuthException */
    public function identity(string $provider, Request $request): SocialIdentity
    {
        if ($error = $request->query('error')) {
            // access_denied = the person pressed Cancel / declined consent. Anything else is a provider-side error.
            throw in_array($error, ['access_denied', 'user_denied', 'user_cancelled_login', 'user_cancelled_authorize'], true)
                ? SocialAuthException::denied()
                : SocialAuthException::providerError();
        }

        if ($this->providers->fakeEnabled()) {
            return $this->fakeIdentity($provider, $request);
        }

        if (! is_string($request->query('code')) || $request->query('code') === '' || ! is_string($request->query('state'))) {
            throw SocialAuthException::badState();
        }

        try {
            $user = $this->driver($provider)->user();
        } catch (InvalidStateException) {
            throw SocialAuthException::badState();
        } catch (\Throwable) {
            throw SocialAuthException::providerError();
        }

        $raw = (array) $user->getRaw();
        $id = (string) $user->getId();
        if ($id === '') {
            throw SocialAuthException::providerError();
        }

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $id,
            email: filled($user->getEmail()) ? strtolower(trim((string) $user->getEmail())) : null,
            emailVerified: $this->reportedVerified($provider, $raw),
            name: filled($user->getName()) ? (string) $user->getName() : null,
            givenName: $raw['given_name'] ?? $raw['first_name'] ?? null,
            familyName: $raw['family_name'] ?? $raw['last_name'] ?? null,
            avatarUrl: filled($user->getAvatar()) ? (string) $user->getAvatar() : null,
        );
    }

    /**
     * Exactly what the provider claims, never invented: Google reports `email_verified`; Facebook reports nothing
     * we can rely on, so it is passed as false (an existing account with that email then requires the password
     * confirmation step instead of being merged).
     *
     * @param  array<string, mixed>  $raw
     */
    private function reportedVerified(string $provider, array $raw): bool
    {
        if ($provider === 'google') {
            $v = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

            return $v === true || $v === 'true';
        }

        return false;
    }

    private function driver(string $provider): AbstractProvider
    {
        $cfg = (array) config("social.providers.$provider");
        $config = [
            'client_id' => (string) ($cfg['client_id'] ?? ''),
            'client_secret' => (string) ($cfg['client_secret'] ?? ''),
            'redirect' => $this->callbackUrl($provider),
        ];
        $factory = app('Laravel\Socialite\Contracts\Factory');

        $driver = match ($provider) {
            'google' => $factory->buildProvider(GoogleProvider::class, $config)->enablePKCE(),
            'facebook' => $factory->buildProvider(FacebookProvider::class, $config)->fields(['name', 'first_name', 'last_name', 'email', 'picture.width(400)']),
            default => throw SocialAuthException::unavailable(),
        };

        /** @var SocialiteProvider&AbstractProvider $driver */
        return $driver->scopes((array) ($cfg['scopes'] ?? []));
    }

    // ---- fake provider (dev/test only) ---------------------------------------------------------------------

    /** Personas offered by the local fake provider: code => [label, description]. */
    public const PERSONAS = [
        'new' => ['New customer', 'A verified email, full name, no account yet. Shows consent, then phone.'],
        'returning' => ['Returning customer', 'Signs straight in once the account exists (run "New customer" first).'],
        'noemail' => ['No email shared', 'The provider gives no email: you will be asked for email and phone.'],
        'linkrequired' => ['Existing account, unverified', 'The provider vouches for demo@007resort.test, an account that never verified its email: the emailed-code step appears (Google; Facebook emails are never trusted).'],
        'denied' => ['Cancel at the provider', 'Simulates pressing Cancel on the consent screen.'],
    ];

    private function fakeIdentity(string $provider, Request $request): SocialIdentity
    {
        $expected = $request->session()->pull(self::FAKE_STATE_KEY);
        $state = $request->query('state');
        if (! is_string($expected) || ! is_string($state) || ! hash_equals($expected, $state)) {
            throw SocialAuthException::badState();
        }

        $persona = (string) $request->query('code');
        $label = ucfirst($provider);
        $base = ['provider' => $provider, 'givenName' => 'Amaka', 'familyName' => 'Okafor', 'name' => 'Amaka Okafor', 'avatarUrl' => null];

        return SocialIdentity::fromArray(match ($persona) {
            // Like the real providers: Google vouches for the email, Facebook does not (so it is sent as unverified).
            'new', 'returning' => $base + ['providerUserId' => "fake-$provider-amaka", 'email' => 'amaka.okafor@example.test', 'emailVerified' => $provider === 'google'],
            'noemail' => ['providerUserId' => "fake-$provider-noemail", 'email' => null, 'emailVerified' => false, 'name' => "$label Friend", 'givenName' => $label, 'familyName' => 'Friend'] + $base,
            'linkrequired' => ['providerUserId' => "fake-$provider-linkrequired", 'email' => 'demo@007resort.test', 'emailVerified' => $provider === 'google', 'name' => 'Demo Guest', 'givenName' => 'Demo', 'familyName' => 'Guest'] + $base,
            'denied' => throw SocialAuthException::denied(),
            default => throw SocialAuthException::badState(),
        });
    }
}

<?php

namespace App\Services\Online;

use App\Services\R007Api\R007ApiClient;
use Illuminate\Contracts\Session\Session;

/**
 * Customer identity lives in the API (separate from staff). The access token is
 * kept in the server-side session only. PROPOSED API: /customer/auth/*.
 */
class CustomerService
{
    public const PROFILE_KEY = 'r007.customer';

    public function __construct(private readonly R007ApiClient $api, private readonly Session $session) {}

    /** @param array{name: string, email: string, phone: string, password: string} $data */
    public function register(array $data, string $idempotencyKey): array
    {
        return $this->api->post('customer/auth/register', $data, $idempotencyKey);
    }

    public function login(string $email, string $password): array
    {
        $result = $this->api->post('customer/auth/login', ['email' => $email, 'password' => $password]);
        $this->store($result);

        return $result;
    }

    /**
     * Sign in with an API session payload obtained another way (social login). Uses exactly the same storage and
     * session-id rotation as the password login above.
     *
     * @param  array<string, mixed>  $result  {accessToken, customer}
     */
    public function signInFromApi(array $result): void
    {
        $this->store($result);
    }

    public function verify(string $email, string $code): array
    {
        $result = $this->api->post('customer/auth/verify', ['email' => $email, 'code' => $code]);
        if (isset($result['accessToken'])) {
            $this->store($result);
        }

        return $result;
    }

    public function resend(string $email): void
    {
        $this->api->post('customer/auth/verify/resend', ['email' => $email]);
    }

    public function logout(): void
    {
        try {
            if ($this->check()) {
                $this->api->post('customer/auth/logout');
            }
        } catch (\Throwable) {
            // Local sign-out must always succeed.
        }
        $this->forget();
    }

    /** Refresh the cached profile from the API (also validates the token). */
    public function refresh(): ?array
    {
        $me = $this->api->get('customer/me');
        $this->session->put(self::PROFILE_KEY, $me);

        return $me;
    }

    public function check(): bool
    {
        return is_string($this->session->get(config('r007.api.session_token_key'))) && $this->session->has(self::PROFILE_KEY);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $u = $this->session->get(self::PROFILE_KEY);

        return is_array($u) ? $u : null;
    }

    public function forget(): void
    {
        $this->session->forget([config('r007.api.session_token_key'), self::PROFILE_KEY]);
    }

    private function store(array $result): void
    {
        $this->session->migrate(true); // new session id on privilege change
        $this->session->put(config('r007.api.session_token_key'), (string) ($result['accessToken'] ?? ''));
        $this->session->put(self::PROFILE_KEY, (array) ($result['customer'] ?? []));
    }
}

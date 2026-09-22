<?php

namespace App\Services\OtuekeApi;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin HTTP client for the Otueke API (/api/v1/...).
 *
 * All business operations (payments, refunds, inventory, tickets, bookings,
 * memberships, order state, ...) MUST go through this client. This app never
 * writes business data to a database of its own.
 *
 * - JSON in / JSON out.
 * - Bearer token is read from the server-side session (never the browser).
 * - Every mutating request carries an Idempotency-Key so the API can safely
 *   de-duplicate retries. Pass an explicit key when retrying the same logical
 *   operation (e.g. a payment form re-submitted after a timeout).
 * - Error responses (RFC 7807 problem details) become OtuekeApiException.
 * - Monetary amounts arrive as decimal strings: never cast them to float.
 *
 * NOTE: duplicated in otueke-admin-web and otueke-booking-web during Phase 0.
 * To be extracted into a shared private Composer package once stable.
 */
class OtuekeApiClient
{
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /**
     * @param  array<string, mixed>  $config  The "otueke.api" config array.
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Session $session = null,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function post(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, ['json' => $data], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function put(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->send('PUT', $path, ['json' => $data], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function patch(string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->send('PATCH', $path, ['json' => $data], $idempotencyKey);
    }

    /**
     * @return array<mixed>
     */
    public function delete(string $path, ?string $idempotencyKey = null): array
    {
        return $this->send('DELETE', $path, [], $idempotencyKey);
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/').'/'.trim((string) ($this->config['prefix'] ?? '/api/v1'), '/');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<mixed>
     */
    protected function send(string $method, string $path, array $options, ?string $idempotencyKey = null): array
    {
        $request = $this->pendingRequest();

        if ($method !== 'GET') {
            $request->withHeaders([self::IDEMPOTENCY_HEADER => $idempotencyKey ?? (string) Str::uuid()]);
        }

        try {
            /** @var Response $response */
            $response = $request->send($method, ltrim($path, '/'), $options);
        } catch (ConnectionException $e) {
            throw OtuekeApiException::unreachable($e);
        }

        if ($response->failed()) {
            throw OtuekeApiException::fromResponse($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function pendingRequest(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Accept' => 'application/json, application/problem+json',
                'X-Otueke-Client' => (string) ($this->config['client_id'] ?? ''),
                'X-Request-Id' => (string) Str::uuid(),
            ])
            ->timeout((int) ($this->config['timeout'] ?? 10))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 3));

        $token = $this->token();

        if ($token !== null && $token !== '') {
            $request->withToken($token);
        }

        return $request;
    }

    protected function token(): ?string
    {
        $key = (string) ($this->config['session_token_key'] ?? 'otueke.api_token');
        $token = $this->session?->get($key);

        return is_string($token) ? $token : null;
    }
}

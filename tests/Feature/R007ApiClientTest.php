<?php

namespace Tests\Feature;

use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class R007ApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'r007.api.base_url' => 'https://api.r007.test',
            'r007.api.prefix' => '/api/v1',
        ]);

        $this->app['session.store']->put('r007.api_token', 'test-access-token');
    }

    public function test_post_uses_base_url_bearer_token_and_idempotency_key(): void
    {
        Http::fake([
            'api.r007.test/*' => Http::response(['id' => 'abc', 'amount' => '1500.00'], 201),
        ]);

        $result = $this->app->make(R007ApiClient::class)->post('ping', ['hello' => 'world']);

        $this->assertSame(['id' => 'abc', 'amount' => '1500.00'], $result);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.r007.test/api/v1/ping'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request->hasHeader('Idempotency-Key')
                && Str::isUuid($request->header('Idempotency-Key')[0])
                && $request['hello'] === 'world';
        });
    }

    public function test_explicit_idempotency_key_is_forwarded(): void
    {
        Http::fake(['api.r007.test/*' => Http::response([], 200)]);

        $this->app->make(R007ApiClient::class)->put('things/1', [], 'fixed-key-123');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'fixed-key-123'));
    }

    public function test_get_does_not_send_idempotency_key(): void
    {
        Http::fake(['api.r007.test/*' => Http::response(['status' => 'ok'], 200)]);

        $this->app->make(R007ApiClient::class)->get('health');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://api.r007.test/api/v1/health')
                && ! $request->hasHeader('Idempotency-Key');
        });
    }

    public function test_problem_details_response_is_mapped_to_exception(): void
    {
        Http::fake([
            'api.r007.test/*' => Http::response([
                'type' => 'https://r007.example/problems/validation',
                'title' => 'Validation failed',
                'status' => 422,
                'detail' => 'One or more fields are invalid.',
                'errors' => ['quantity' => ['Must be greater than zero.']],
                'traceId' => '00-abc-01',
            ], 422, ['Content-Type' => 'application/problem+json']),
        ]);

        try {
            $this->app->make(R007ApiClient::class)->post('things', ['quantity' => 0]);
            $this->fail('Expected R007ApiException');
        } catch (R007ApiException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('Validation failed', $e->title);
            $this->assertSame('One or more fields are invalid.', $e->detail);
            $this->assertSame('https://r007.example/problems/validation', $e->type);
            $this->assertSame(['quantity' => ['Must be greater than zero.']], $e->errors());
            $this->assertSame('00-abc-01', $e->extensions['traceId']);
        }
    }
}

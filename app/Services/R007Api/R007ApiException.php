<?php

namespace App\Services\R007Api;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Raised when the 007 Resort & Spa API returns an error (RFC 7807 problem
 * details) or cannot be reached. The API is the source of truth for business
 * rules, so callers should surface {@see $title}/{@see $detail} rather than
 * re-implement validation locally.
 */
class R007ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extensions  Additional problem members (e.g. errors, traceId).
     */
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?string $type = null,
        public readonly ?string $instance = null,
        public readonly array $extensions = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail ? "{$title}: {$detail}" : $title, $status, $previous);
    }

    /**
     * Build an exception from a failed API response. RFC 7807 bodies
     * (application/problem+json) are mapped member-by-member; anything else
     * falls back to the HTTP status line.
     */
    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $known = ['type', 'title', 'status', 'detail', 'instance'];

        return new self(
            status: (int) ($body['status'] ?? $response->status()),
            title: (string) ($body['title'] ?? $response->reason() ?: '007 Resort & Spa API error'),
            detail: isset($body['detail']) ? (string) $body['detail'] : null,
            type: isset($body['type']) ? (string) $body['type'] : null,
            instance: isset($body['instance']) ? (string) $body['instance'] : null,
            extensions: array_diff_key($body, array_flip($known)),
        );
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(
            status: 0,
            title: '007 Resort & Spa API unreachable',
            detail: $previous->getMessage(),
            previous: $previous,
        );
    }

    /**
     * Validation errors as returned by the API (field => messages), if any.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = $this->extensions['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}

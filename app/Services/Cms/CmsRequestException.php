<?php

namespace App\Services\Cms;

use RuntimeException;

/** The CMS answered with a 4xx problem (validation, rate limit, invalid token...). Never used for 404 on reads. */
class CmsRequestException extends RuntimeException
{
    /** @param array<string, list<string>|string> $errors */
    public function __construct(public readonly int $status, public readonly string $problemCode, string $message = '', public readonly array $errors = [], public readonly ?int $retryAfter = null)
    {
        parent::__construct($message !== '' ? $message : $problemCode, $status);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Http\Exceptions;

use Throwable;

final class HttpRateLimitedException extends HttpException
{
    private ?int $retryAfterSeconds;

    public function __construct(
        string $message = 'Target server rate limited requests (HTTP 429).',
        ?int $retryAfterSeconds = null,
        int $code = 429,
        ?Throwable $previous = null
    ) {
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct($message, 'HTTP_RATE_LIMITED', $code, $previous);
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}

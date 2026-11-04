<?php

declare(strict_types=1);

namespace SitePortal\Core\Analysis\Exceptions;

use RuntimeException;

final class AnalysisRateLimitedException extends RuntimeException
{
    private int $retryAfterSeconds;

    public function __construct(string $message, int $retryAfterSeconds = 60)
    {
        parent::__construct($message, 429);
        $this->retryAfterSeconds = max(1, $retryAfterSeconds);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}

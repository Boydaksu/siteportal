<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Exceptions;

use Throwable;

final class RobotsUnavailableException extends RobotsException
{
    private string $host;
    private int $statusCode;

    public function __construct(
        string $host,
        int $statusCode = 0,
        string $message = 'Target robots.txt could not be fetched or target server returned an error.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->host = $host;
        $this->statusCode = $statusCode;
        parent::__construct($message, 'TECH_ROBOTS_UNAVAILABLE', $code, $previous);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Exceptions;

use RuntimeException;
use Throwable;

class RobotsException extends RuntimeException
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode = 'ROBOTS_ERROR',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

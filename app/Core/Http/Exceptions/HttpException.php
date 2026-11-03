<?php

declare(strict_types=1);

namespace SitePortal\Core\Http\Exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode = 'HTTP_ERROR',
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

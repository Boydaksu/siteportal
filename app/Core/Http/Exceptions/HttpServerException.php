<?php

declare(strict_types=1);

namespace SitePortal\Core\Http\Exceptions;

use Throwable;

final class HttpServerException extends HttpException
{
    private int $statusCode;

    public function __construct(
        string $message,
        int $statusCode = 500,
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        parent::__construct($message, 'HTTP_SERVER_ERROR', $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

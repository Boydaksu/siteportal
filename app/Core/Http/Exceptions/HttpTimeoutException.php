<?php

declare(strict_types=1);

namespace SitePortal\Core\Http\Exceptions;

use Throwable;

final class HttpTimeoutException extends HttpException
{
    public function __construct(string $message = 'HTTP request timed out.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, 'HTTP_TIMEOUT', $code, $previous);
    }
}

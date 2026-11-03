<?php

declare(strict_types=1);

namespace SitePortal\Core\Http\Exceptions;

use Throwable;

final class BodyTooLargeException extends HttpException
{
    private int $bytesReceived;
    private int $limitBytes;

    public function __construct(
        string $message,
        int $bytesReceived = 0,
        int $limitBytes = 0,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->bytesReceived = $bytesReceived;
        $this->limitBytes = $limitBytes;
        parent::__construct($message, 'BODY_TOO_LARGE', $code, $previous);
    }

    public function getBytesReceived(): int
    {
        return $this->bytesReceived;
    }

    public function getLimitBytes(): int
    {
        return $this->limitBytes;
    }
}

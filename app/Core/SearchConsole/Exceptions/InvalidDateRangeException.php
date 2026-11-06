<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Exceptions;

use Throwable;

final class InvalidDateRangeException extends SearchConsoleException
{
    public function __construct(
        string $message = 'Geçersiz Search Analytics tarih aralığı.',
        int $code = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

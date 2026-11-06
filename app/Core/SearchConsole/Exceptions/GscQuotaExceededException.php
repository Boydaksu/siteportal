<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Exceptions;

use Throwable;

final class GscQuotaExceededException extends SearchConsoleException
{
    public function __construct(
        string $message = 'Google Search Console API kota limiti aşıldı (HTTP 429).',
        int $code = 429,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

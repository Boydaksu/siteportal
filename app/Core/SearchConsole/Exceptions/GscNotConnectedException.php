<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Exceptions;

use Throwable;

final class GscNotConnectedException extends SearchConsoleException
{
    public function __construct(
        string $message = 'Google Search Console bağlantısı bulunamadı veya yetkilendirilmemiş.',
        int $code = 401,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

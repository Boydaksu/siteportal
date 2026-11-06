<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Exceptions;

use Throwable;

final class GscPermissionException extends SearchConsoleException
{
    public function __construct(
        string $message = 'Bu alan adı için Google Search Console erişim yetkisi bulunmuyor.',
        int $code = 403,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

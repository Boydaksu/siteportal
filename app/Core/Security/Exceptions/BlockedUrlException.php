<?php

declare(strict_types=1);

namespace SitePortal\Core\Security\Exceptions;

final class BlockedUrlException extends SecurityException
{
    protected string $errorCode = 'URL_BLOCKED';

    public function __construct(
        string $message = 'Bu adres güvenlik politikamız nedeniyle engellenmiştir.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Security\Exceptions;

final class DnsResolutionException extends SecurityException
{
    protected string $errorCode = 'DNS_FAILED';

    public function __construct(
        string $message = 'Alan adı DNS üzerinden çözümlenemedi.',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

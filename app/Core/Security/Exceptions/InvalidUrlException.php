<?php

declare(strict_types=1);

namespace SitePortal\Core\Security\Exceptions;

final class InvalidUrlException extends SecurityException
{
    protected string $errorCode = 'INVALID_URL';

    public function __construct(string $message = 'Geçersiz URL biçimi.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

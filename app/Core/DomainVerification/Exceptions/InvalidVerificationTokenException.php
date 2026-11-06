<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Exceptions;

final class InvalidVerificationTokenException extends DomainVerificationException
{
    public function __construct(string $message = 'Geçersiz veya kullanılmış doğrulama tokenı.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}

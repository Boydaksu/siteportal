<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Exceptions;

final class VerificationTokenExpiredException extends DomainVerificationException
{
    public function __construct(string $message = 'Domain doğrulama token süresi dolmuştur.', int $code = 410)
    {
        parent::__construct($message, $code);
    }
}

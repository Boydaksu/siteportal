<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Exceptions;

final class VerificationChallengeNotFoundException extends DomainVerificationException
{
    public function __construct(string $message = 'Domain doğrulama isteği bulunamadı.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Exceptions;

final class DomainMismatchException extends DomainVerificationException
{
    public function __construct(string $message = 'Doğrulanan hedef host ile kayıtlı site uyuşmuyor.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Exceptions;

final class VerificationPermissionException extends DomainVerificationException
{
    public function __construct(
        string $message = 'Bu domaini doğrulama veya yönetme yetkiniz bulunmuyor.',
        int $code = 403
    ) {
        parent::__construct($message, $code);
    }
}

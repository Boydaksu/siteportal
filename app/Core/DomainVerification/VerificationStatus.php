<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case FAILED = 'failed';
    case REVOKED = 'revoked';

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }
}

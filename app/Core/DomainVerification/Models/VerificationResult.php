<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Models;

final class VerificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $messageKey,
        public readonly ?string $details = null
    ) {
    }

    public static function success(
        string $messageKey = 'domain_verification.verified_successfully',
        ?string $details = null
    ): self {
        return new self(true, $messageKey, $details);
    }

    public static function failure(string $messageKey, ?string $details = null): self
    {
        return new self(false, $messageKey, $details);
    }
}

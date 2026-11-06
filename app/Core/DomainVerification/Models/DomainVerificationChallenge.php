<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification\Models;

use SitePortal\Core\DomainVerification\VerificationMethod;
use SitePortal\Core\DomainVerification\VerificationStatus;

final class DomainVerificationChallenge
{
    public const TOKEN_PREFIX = 'siteportal-site-verification=';
    public const HTTP_WELL_KNOWN_PATH = '/.well-known/siteportal-verify.txt';

    public function __construct(
        public readonly int $id,
        public readonly int $siteId,
        public readonly int $userId,
        public readonly VerificationMethod $method,
        public readonly string $token,
        public readonly VerificationStatus $status,
        public readonly string $expiresAt,
        public readonly ?string $lastCheckedAt = null,
        public readonly ?string $verifiedAt = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $createdAt = null
    ) {
    }

    /**
     * Expected DNS record value or HTTP file content.
     */
    public function getExpectedRecordValue(): string
    {
        return self::TOKEN_PREFIX . $this->token;
    }

    /**
     * Relative path for HTTP verification file.
     */
    public function getHttpWellKnownPath(): string
    {
        return self::HTTP_WELL_KNOWN_PATH;
    }

    public function isExpired(): bool
    {
        $time = strtotime($this->expiresAt);
        return $time !== false && $time < time();
    }
}

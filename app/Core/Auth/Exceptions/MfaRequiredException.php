<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class MfaRequiredException extends AuthException
{
    private int $userId;

    public function __construct(int $userId, string $message = 'auth.mfa_required', int $code = 401)
    {
        $this->userId = $userId;
        parent::__construct($message, $code);
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}

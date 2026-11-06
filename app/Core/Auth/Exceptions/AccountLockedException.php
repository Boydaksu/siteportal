<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class AccountLockedException extends AuthException
{
    private int $secondsRemaining;

    public function __construct(int $secondsRemaining, string $message = 'auth.account_locked', int $code = 429)
    {
        $this->secondsRemaining = $secondsRemaining;
        parent::__construct($message, $code);
    }

    public function getSecondsRemaining(): int
    {
        return $this->secondsRemaining;
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class EmailNotVerifiedException extends AuthException
{
    private int $userId;
    private string $email;

    public function __construct(
        int $userId,
        string $email,
        string $message = 'auth.email_not_verified',
        int $code = 403
    ) {
        $this->userId = $userId;
        $this->email = $email;
        parent::__construct($message, $code);
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

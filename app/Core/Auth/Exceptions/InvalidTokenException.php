<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class InvalidTokenException extends AuthException
{
    public function __construct(string $message = 'auth.invalid_or_expired_token', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}

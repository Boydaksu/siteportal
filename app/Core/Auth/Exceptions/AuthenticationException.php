<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class AuthenticationException extends AuthException
{
    public function __construct(string $message = 'auth.invalid_credentials', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}

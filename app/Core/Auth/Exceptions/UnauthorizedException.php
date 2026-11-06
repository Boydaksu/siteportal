<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class UnauthorizedException extends AuthException
{
    public function __construct(string $message = 'auth.unauthorized', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}

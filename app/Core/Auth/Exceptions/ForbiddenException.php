<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Exceptions;

class ForbiddenException extends AuthException
{
    public function __construct(string $message = 'auth.forbidden', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}

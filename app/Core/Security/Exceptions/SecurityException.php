<?php

declare(strict_types=1);

namespace SitePortal\Core\Security\Exceptions;

use Exception;

class SecurityException extends Exception
{
    protected string $errorCode = 'INTERNAL_ERROR';

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

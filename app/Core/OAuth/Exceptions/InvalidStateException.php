<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Exceptions;

final class InvalidStateException extends OAuthException
{
    public function __construct(
        string $message = 'Geçersiz, süresi dolmuş veya önceden kullanılmış OAuth state parametresi.',
        int $code = 400
    ) {
        parent::__construct($message, $code);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Exceptions;

final class OAuthTokenExpiredException extends OAuthException
{
    public function __construct(
        string $message = 'OAuth erişim tokenının süresi dolmuştur.',
        int $code = 401
    ) {
        parent::__construct($message, $code);
    }
}

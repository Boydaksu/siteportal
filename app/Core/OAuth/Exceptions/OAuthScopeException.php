<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Exceptions;

final class OAuthScopeException extends OAuthException
{
    public function __construct(
        string $message = 'İstenen OAuth kapsamları (scopes) güvenlik ilkelerine uygun değil veya yetersiz.',
        int $code = 400
    ) {
        parent::__construct($message, $code);
    }
}

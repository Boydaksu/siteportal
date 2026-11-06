<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Exceptions;

use Throwable;

final class OAuthRevocationException extends OAuthException
{
    public function __construct(
        string $message = 'OAuth erişim izni sağlayıcı tarafında iptal edilemedi.',
        int $code = 502,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Exceptions;

final class TokenDecryptionException extends OAuthException
{
    public function __construct(
        string $message = 'Şifreli token çözülemedi veya veri bütünlüğü bozulmuş.',
        int $code = 500
    ) {
        parent::__construct($message, $code);
    }
}

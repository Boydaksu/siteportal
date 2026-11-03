<?php

declare(strict_types=1);

namespace SitePortal\Core\Security\Exceptions;

final class RedirectException extends SecurityException
{
    public const string CODE_LIMIT_EXCEEDED = 'REDIRECT_LIMIT';
    public const string CODE_LOOP_DETECTED = 'REDIRECT_LOOP';

    public function __construct(
        string $message = 'Yönlendirme hatası.',
        string $errorCode = self::CODE_LIMIT_EXCEEDED,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }
}

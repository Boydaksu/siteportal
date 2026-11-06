<?php

declare(strict_types=1);

namespace SitePortal\Core\SearchConsole\Exceptions;

final class InvalidInspectionUrlException extends SearchConsoleException
{
    public function __construct(string $message = 'Incelenecek URL gecersiz veya site alan adiyla uyumsuz.')
    {
        parent::__construct($message, 400);
    }
}

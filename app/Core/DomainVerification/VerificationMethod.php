<?php

declare(strict_types=1);

namespace SitePortal\Core\DomainVerification;

enum VerificationMethod: string
{
    case DNS_TXT = 'dns_txt';
    case HTTP_FILE = 'http_file';

    public function label(): string
    {
        return match ($this) {
            self::DNS_TXT => 'DNS TXT Kaydı',
            self::HTTP_FILE => 'HTTP Doğrulama Dosyası',
        };
    }
}

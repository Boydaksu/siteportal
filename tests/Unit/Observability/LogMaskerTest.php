<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Observability;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Observability\LogMasker;

final class LogMaskerTest extends TestCase
{
    private LogMasker $masker;

    protected function setUp(): void
    {
        $this->masker = new LogMasker();
    }

    public function testMaskArrayRedactsSensitiveKeys(): void
    {
        $context = [
            'username' => 'admin',
            'password' => 'SuperSecret123!',
            'api_key' => 'ak_live_abcdef1234567890',
            'access_token' => 'at_987654321',
            'details' => [
                'auth_token' => 'secret_jwt_token',
                'credit_card' => '4532-1234-5678-9010',
                'public_id' => 'analysis_123',
            ],
        ];

        $masked = $this->masker->maskArray($context);

        self::assertSame('admin', $masked['username']);
        self::assertSame('[REDACTED]', $masked['password']);
        self::assertSame('[REDACTED]', $masked['api_key']);
        self::assertSame('[REDACTED]', $masked['access_token']);

        self::assertIsArray($masked['details']);
        self::assertSame('[REDACTED]', $masked['details']['auth_token']);
        self::assertSame('[REDACTED]', $masked['details']['credit_card']);
        self::assertSame('analysis_123', $masked['details']['public_id']);
    }

    public function testMaskStringRedactsBearerTokens(): void
    {
        $message = 'User sent header Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.xyz';
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.xyz', $masked);
        self::assertSame('User sent header Authorization: Bearer [REDACTED]', $masked);
    }

    public function testMaskStringRedactsBasicAuth(): void
    {
        $message = 'Sending request with Authorization: Basic YWRtaW46c2VjcmV0MTIz to upstream';
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('YWRtaW46c2VjcmV0MTIz', $masked);
        self::assertSame('Sending request with Authorization: Basic [REDACTED] to upstream', $masked);
    }

    public function testMaskStringRedactsPasswordsInUrls(): void
    {
        $message = 'Connected to mysql://siteportal_user:p@ssw0rd123@db.internal:3306/siteportal_prod';
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('p@ssw0rd123', $masked);
        self::assertSame('Connected to mysql://siteportal_user:[REDACTED]@db.internal:3306/siteportal_prod', $masked);
    }

    public function testMaskStringRedactsQueryParameterSecrets(): void
    {
        $message = 'Crawled https://api.target.com/v1/crawl?token=secret_param_value&max=10';
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('secret_param_value', $masked);
        self::assertSame('Crawled https://api.target.com/v1/crawl?token=[REDACTED]&max=10', $masked);
    }

    public function testMaskStringRedactsPemPrivateKeys(): void
    {
        $pem = "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA0m\nfakeKeyContentHere\n-----END RSA PRIVATE KEY-----";
        $message = "Loaded SSL certificate key:\n" . $pem;
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('fakeKeyContentHere', $masked);
        self::assertStringContainsString('[REDACTED PRIVATE KEY]', $masked);
    }

    public function testMaskStringRedactsCreditCardNumbers(): void
    {
        $message = 'Payment attempt with card 4532 0150 1234 5678 failed';
        $masked = $this->masker->maskString($message);

        self::assertStringNotContainsString('4532 0150 1234 5678', $masked);
        self::assertSame('Payment attempt with card [REDACTED CARD] failed', $masked);
    }

    public function testMaskGenericDataDispatchesProperly(): void
    {
        self::assertSame('[REDACTED]', $this->masker->mask(['password' => 'secret'])['password']);
        self::assertSame('Bearer [REDACTED]', $this->masker->mask('Bearer my_secret_token'));
        self::assertSame(12345, $this->masker->mask(12345));
    }
}

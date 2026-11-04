<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\Session;

final class CsrfTest extends TestCase
{
    private Session $session;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->session = Session::createInMemory();
        $this->csrf = new Csrf($this->session);
    }

    public function testGetTokenGeneratesCryptographicallySecureToken(): void
    {
        $token = $this->csrf->getToken();

        self::assertNotEmpty($token);
        self::assertSame(64, strlen($token)); // 32 random bytes in hex
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        // Successive calls return same active token
        self::assertSame($token, $this->csrf->getToken());
    }

    public function testValidateTokenAcceptsValidToken(): void
    {
        $token = $this->csrf->getToken();
        self::assertTrue($this->csrf->validateToken($token));
    }

    public function testValidateTokenRejectsTamperedOrEmptyToken(): void
    {
        $token = $this->csrf->getToken();

        self::assertFalse($this->csrf->validateToken(null));
        self::assertFalse($this->csrf->validateToken(''));
        self::assertFalse($this->csrf->validateToken('invalid_token_value'));
        self::assertFalse($this->csrf->validateToken(substr($token, 0, -1) . '0'));
    }

    public function testRefreshTokenCreatesDifferentToken(): void
    {
        $token1 = $this->csrf->getToken();
        $token2 = $this->csrf->refreshToken();

        self::assertNotSame($token1, $token2);
        self::assertTrue($this->csrf->validateToken($token2));
        self::assertFalse($this->csrf->validateToken($token1));
    }

    public function testGetHiddenInputHtmlRendersProperInput(): void
    {
        $html = $this->csrf->getHiddenInputHtml();
        $token = $this->csrf->getToken();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('name="_csrf"', $html);
        self::assertStringContainsString('value="' . $token . '"', $html);
    }
}

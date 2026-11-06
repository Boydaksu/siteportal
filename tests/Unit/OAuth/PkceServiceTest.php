<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Security\PkceService;

final class PkceServiceTest extends TestCase
{
    public function testGenerateCodeVerifierWithinRfcRange(): void
    {
        $pkce = new PkceService();
        $verifier = $pkce->generateCodeVerifier(64);

        self::assertSame(64, strlen($verifier));
        // Must contain only unreserved characters [A-Za-z0-9_-.~]
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_\-\.~]+$/', $verifier);
    }

    public function testGenerateCodeVerifierThrowsOnInvalidLength(): void
    {
        $pkce = new PkceService();

        $this->expectException(OAuthException::class);
        $pkce->generateCodeVerifier(42); // Below RFC minimum of 43
    }

    public function testGenerateCodeChallengeMatchesRfc7636AppendixBTestVector(): void
    {
        $pkce = new PkceService();

        // Exact test vector from RFC 7636 Appendix B:
        // code_verifier: "dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"
        // code_challenge: "E9Melhoa2OwvFrGMTJguCH5rtG6jQu-hbtbmqimvFiM"
        $rfcVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $expectedChallenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

        $actualChallenge = $pkce->generateCodeChallenge($rfcVerifier);

        self::assertSame($expectedChallenge, $actualChallenge);
    }

    public function testGenerateStateReturnsSecureRandomString(): void
    {
        $pkce = new PkceService();
        $state1 = $pkce->generateState();
        $state2 = $pkce->generateState();

        self::assertSame(64, strlen($state1)); // 32 bytes hex
        self::assertNotSame($state1, $state2);
    }
}

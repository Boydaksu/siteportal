<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Exceptions\TokenDecryptionException;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;

final class TokenEncryptionServiceTest extends TestCase
{
    private const TEST_KEY = 'test_secret_key_32_bytes_long_exact_1234!';

    public function testEncryptAndDecryptSuccess(): void
    {
        $service = new TokenEncryptionService(self::TEST_KEY);
        $plain = 'ya29.a0AfH6SMD_sensitive_refresh_token_example';

        $encrypted = $service->encrypt($plain);

        self::assertNotSame($plain, $encrypted);
        self::assertStringNotContainsString('ya29.', $encrypted);

        $decrypted = $service->decrypt($encrypted);
        self::assertSame($plain, $decrypted);
    }

    public function testDifferentIvPerEncryption(): void
    {
        $service = new TokenEncryptionService(self::TEST_KEY);
        $plain = 'same_secret_token';

        $enc1 = $service->encrypt($plain);
        $enc2 = $service->encrypt($plain);

        self::assertNotSame($enc1, $enc2);
        self::assertSame($plain, $service->decrypt($enc1));
        self::assertSame($plain, $service->decrypt($enc2));
    }

    public function testTamperedCiphertextThrowsTokenDecryptionException(): void
    {
        $service = new TokenEncryptionService(self::TEST_KEY);
        $encrypted = $service->encrypt('secret');

        /** @var array{iv: string, tag: string, data: string} $payload */
        $payload = json_decode($encrypted, true);
        $rawCipher = base64_decode($payload['data']);
        $rawCipher[0] = $rawCipher[0] === 'A' ? 'B' : 'A'; // flip byte
        $payload['data'] = base64_encode($rawCipher);

        $tamperedJson = json_encode($payload);

        $this->expectException(TokenDecryptionException::class);
        $service->decrypt((string) $tamperedJson);
    }

    public function testTamperedTagThrowsTokenDecryptionException(): void
    {
        $service = new TokenEncryptionService(self::TEST_KEY);
        $encrypted = $service->encrypt('secret');

        /** @var array{iv: string, tag: string, data: string} $payload */
        $payload = json_decode($encrypted, true);
        $rawTag = base64_decode($payload['tag']);
        $rawTag[0] = $rawTag[0] === 'X' ? 'Y' : 'X';
        $payload['tag'] = base64_encode($rawTag);

        $tamperedJson = json_encode($payload);

        $this->expectException(TokenDecryptionException::class);
        $service->decrypt((string) $tamperedJson);
    }

    public function testInvalidJsonOrFormatThrowsTokenDecryptionException(): void
    {
        $service = new TokenEncryptionService(self::TEST_KEY);

        $this->expectException(TokenDecryptionException::class);
        $service->decrypt('invalid-not-json');
    }

    public function testMissingKeyThrowsOAuthException(): void
    {
        $this->expectException(OAuthException::class);
        new TokenEncryptionService('');
    }

    public function testDifferentKeyFailsDecryption(): void
    {
        $service1 = new TokenEncryptionService('key_1_secret');
        $service2 = new TokenEncryptionService('key_2_secret');

        $encrypted = $service1->encrypt('my_token');

        $this->expectException(TokenDecryptionException::class);
        $service2->decrypt($encrypted);
    }
}

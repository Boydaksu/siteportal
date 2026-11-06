<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Security;

use JsonException;
use SitePortal\Core\Config;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Exceptions\TokenDecryptionException;
use Throwable;

final class TokenEncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12; // 96-bit recommended for GCM
    private const TAG_LENGTH = 16; // 128-bit authentication tag

    private string $derivedKey;

    public function __construct(?string $key = null)
    {
        $rawKey = $key;
        if ($rawKey === null || trim($rawKey) === '') {
            $rawKey = (string) Config::getInstance()->get('oauth.encryption_key', '');
        }

        if (trim($rawKey) === '') {
            throw new OAuthException('OAuth şifreleme anahtarı (encryption_key) yapılandırılmamış.');
        }

        // 32-byte (256-bit) anahtar türetimi
        $this->derivedKey = hash('sha256', $rawKey, true);
    }

    /**
     * Encrypts plain text using AES-256-GCM with a random IV and authentication tag.
     */
    public function encrypt(string $plainText): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new OAuthException('Token şifreleme işlemi başarısız oldu.');
        }

        try {
            return json_encode([
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'data' => base64_encode($ciphertext),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new OAuthException('Şifrelenmiş veri serileştirilemedi: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypts AES-256-GCM payload and verifies authentication tag.
     * Throws TokenDecryptionException on tampering or invalid tag.
     */
    public function decrypt(string $payloadJson): string
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new TokenDecryptionException('Şifrelenmiş veri formatı geçersiz.');
        }

        if (
            !is_array($decoded)
            || !isset($decoded['iv'], $decoded['tag'], $decoded['data'])
            || !is_string($decoded['iv'])
            || !is_string($decoded['tag'])
            || !is_string($decoded['data'])
        ) {
            throw new TokenDecryptionException('Eksik şifreleme alanları.');
        }

        $iv = base64_decode($decoded['iv'], true);
        $tag = base64_decode($decoded['tag'], true);
        $ciphertext = base64_decode($decoded['data'], true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new TokenDecryptionException('Base64 çözme hatası.');
        }

        if (strlen($iv) !== self::IV_LENGTH || strlen($tag) !== self::TAG_LENGTH) {
            throw new TokenDecryptionException('Geçersiz IV veya tag uzunluğu.');
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new TokenDecryptionException('Şifreli token doğrulanamadı (bütünlük etiketi eşleşmedi).');
        }

        return $decrypted;
    }
}

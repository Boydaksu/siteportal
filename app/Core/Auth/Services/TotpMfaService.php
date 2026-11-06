<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Services;

use RuntimeException;

final class TotpMfaService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private int $period;
    private int $digits;
    private int $window;

    public function __construct(int $period = 30, int $digits = 6, int $window = 1)
    {
        $this->period = max(15, $period);
        $this->digits = max(6, min(8, $digits));
        $this->window = max(0, min(3, $window));
    }

    /**
     * 16 baytlık (128 bit) kriptografik güvenli rastgele Base32 secret anahtarı üretir.
     */
    public function generateSecret(int $length = 16): string
    {
        $safeLength = max(1, $length);
        $bytes = random_bytes($safeLength);
        return $this->base32Encode($bytes);
    }

    /**
     * Belirli bir zaman damgası ve secret için TOTP kodu üretir.
     */
    public function generateOtp(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $binarySecret = $this->base32Decode($secret);
        $timeSlice = (int) floor($timestamp / $this->period);

        // Pack timeslice into 64-bit big-endian binary string
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $truncatedHash % (10 ** $this->digits);
        return str_pad((string) $otp, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Kullanıcının girdiği TOTP kodunu tolerans penceresi içinde doğrular.
     */
    public function verifyOtp(string $secret, string $code, ?int $timestamp = null): bool
    {
        $cleanCode = trim($code);
        if (strlen($cleanCode) !== $this->digits || !ctype_digit($cleanCode)) {
            return false;
        }

        $timestamp = $timestamp ?? time();

        for ($i = -$this->window; $i <= $this->window; $i++) {
            $sliceTimestamp = $timestamp + ($i * $this->period);
            $expectedOtp = $this->generateOtp($secret, $sliceTimestamp);
            if (hash_equals($expectedOtp, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticator uygulamaları için standart otpauth:// URI üretir.
     */
    public function getOtpAuthUri(string $email, string $secret, string $issuer = 'SitePortal'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($email);
        $encodedIssuer = rawurlencode($issuer);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&period=%d&digits=%d',
            $label,
            $secret,
            $encodedIssuer,
            $this->period,
            $this->digits
        );
    }

    /**
     * 8 adet 8 haneli tek kullanımlık kurtarma kodu üretir.
     *
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $code = bin2hex(random_bytes(4)); // 8 karakter hex
            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Kurtarma kodunu doğrular. Doğrulanırsa kalan hash dizisini döner, aksi halde null.
     *
     * @param list<string> $hashedCodes
     * @return list<string>|null
     */
    public function verifyAndConsumeRecoveryCode(string $code, array $hashedCodes): ?array
    {
        $cleanCode = trim(strtolower($code));
        if ($cleanCode === '') {
            return null;
        }

        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($cleanCode, $hash)) {
                $remaining = $hashedCodes;
                unset($remaining[$index]);
                return array_values($remaining);
            }
        }

        return null;
    }

    private function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);
        $encoded = '';
        foreach ($chunks as $chunk) {
            $chunkLen = strlen($chunk);
            if ($chunkLen < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $index = (int) bindec($chunk);
            $encoded .= self::BASE32_CHARS[$index];
        }

        return $encoded;
    }

    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        if ($b32 === '') {
            return '';
        }

        $binary = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::BASE32_CHARS, $b32[$i]);
            if ($pos === false) {
                throw new RuntimeException('Invalid Base32 character: ' . $b32[$i]);
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 8);
        $decoded = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr((int) bindec($chunk));
            }
        }

        return $decoded;
    }
}

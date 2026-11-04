<?php

declare(strict_types=1);

namespace SitePortal\Core\Observability;

final class LogMasker
{
    private const DEFAULT_MASK = '[REDACTED]';

    /** @var list<string> */
    private array $sensitiveKeys;

    private string $maskValue;

    /**
     * @param list<string>|null $sensitiveKeys
     */
    public function __construct(?array $sensitiveKeys = null, string $maskValue = self::DEFAULT_MASK)
    {
        $this->sensitiveKeys = $sensitiveKeys ?? [
            'password',
            'passwd',
            'pass',
            'secret',
            'token',
            'auth',
            'authorization',
            'cookie',
            'set-cookie',
            'api_key',
            'apikey',
            'access_token',
            'refresh_token',
            'private_key',
            'credentials',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        $this->maskValue = $maskValue;
    }

    /**
     * Mask both message strings and nested context arrays.
     */
    public function mask(mixed $data): mixed
    {
        if (is_string($data)) {
            return $this->maskString($data);
        }

        if (is_array($data)) {
            return $this->maskArray($data);
        }

        return $data;
    }

    /**
     * Mask secrets inside a raw string message.
     */
    public function maskString(string $message): string
    {
        // 1. PEM private keys
        $masked = (string) preg_replace(
            '/-----BEGIN[ A-Z0-9_-]*PRIVATE KEY-----[\s\S]*?-----END[ A-Z0-9_-]*PRIVATE KEY-----/',
            '[REDACTED PRIVATE KEY]',
            $message
        );

        // 2. Bearer tokens (Authorization: Bearer <token>)
        $masked = (string) preg_replace(
            '/\b(Bearer\s+)[a-zA-Z0-9_\-\.~+\/=]+/i',
            '$1' . $this->maskValue,
            $masked
        );

        // 3. Basic auth (Authorization: Basic <base64>)
        $masked = (string) preg_replace(
            '/\b(Basic\s+)[a-zA-Z0-9+\/=]{6,}/i',
            '$1' . $this->maskValue,
            $masked
        );

        // 4. Passwords in URLs (e.g. mysql://user:pass@host:port/db)
        $masked = (string) preg_replace(
            '#([a-zA-Z][a-zA-Z0-9+\-.]*://[^:\s/@]+):(.+?)@([a-zA-Z0-9.\-_]+(?::\d+)?(?:/|$|\s))#',
            '$1:' . $this->maskValue . '@$3',
            $masked
        );

        // 5. Query parameter tokens and secrets (e.g. ?token=xyz or &password=xyz)
        $masked = (string) preg_replace(
            '/([?&](?:password|passwd|token|access_token|refresh_token|api_key|apikey|secret|key)=)[^&\s]+/i',
            '$1' . $this->maskValue,
            $masked
        );

        // 6. Credit card numbers (13 to 19 digits with optional spaces or dashes)
        $masked = (string) preg_replace(
            '/\b(?:\d[ -]*?){13,19}\b/',
            '[REDACTED CARD]',
            $masked
        );

        return $masked;
    }

    /**
     * Mask secrets in associative or indexed arrays recursively.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public function maskArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $result[$key] = $this->maskValue;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                $result[$key] = $this->maskString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $key));

        foreach ($this->sensitiveKeys as $sensitive) {
            $normalizedSensitive = strtolower(str_replace(['-', '_', ' '], '', $sensitive));

            if ($normalized === $normalizedSensitive || str_contains($normalized, $normalizedSensitive)) {
                return true;
            }
        }

        return false;
    }
}

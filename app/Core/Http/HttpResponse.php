<?php

declare(strict_types=1);

namespace SitePortal\Core\Http;

final class HttpResponse
{
    private int $statusCode;
    /** @var array<string, list<string>> */
    private array $headers;
    private string $body;
    private string $effectiveUrl;
    /** @var list<array{url: string, status: int}> */
    private array $redirectChain;
    /** @var array<string, mixed> */
    private array $transferStats;

    /**
     * @param array<string, string|list<string>> $headers
     * @param list<array{url: string, status: int}> $redirectChain
     * @param array<string, mixed> $transferStats
     */
    public function __construct(
        int $statusCode,
        array $headers,
        string $body,
        string $effectiveUrl,
        array $redirectChain = [],
        array $transferStats = []
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->effectiveUrl = $effectiveUrl;
        $this->redirectChain = $redirectChain;
        $this->transferStats = $transferStats;

        $normalizedHeaders = [];
        foreach ($headers as $key => $values) {
            $lowerKey = strtolower(trim((string) $key));
            if (is_array($values)) {
                $normalizedHeaders[$lowerKey] = array_map('trim', $values);
            } else {
                $normalizedHeaders[$lowerKey] = [trim((string) $values)];
            }
        }
        $this->headers = $normalizedHeaders;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getEffectiveUrl(): string
    {
        return $this->effectiveUrl;
    }

    /**
     * @return list<array{url: string, status: int}>
     */
    public function getRedirectChain(): array
    {
        return $this->redirectChain;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower(trim($name))]);
    }

    public function getHeader(string $name): ?string
    {
        $lower = strtolower(trim($name));
        if (!isset($this->headers[$lower]) || empty($this->headers[$lower])) {
            return null;
        }

        return $this->headers[$lower][0];
    }

    /**
     * @return list<string>
     */
    public function getHeaderValues(string $name): array
    {
        $lower = strtolower(trim($name));
        return $this->headers[$lower] ?? [];
    }

    public function getContentType(): ?string
    {
        $header = $this->getHeader('content-type');
        if ($header === null) {
            return null;
        }

        $parts = explode(';', $header, 2);
        return trim($parts[0]);
    }

    public function getCharset(): ?string
    {
        $header = $this->getHeader('content-type');
        if ($header === null) {
            return null;
        }

        if (preg_match('/charset=([^\s;]+)/i', $header, $matches) === 1) {
            return trim($matches[1], "\"' \t\n\r\0\x0B");
        }

        return null;
    }

    /**
     * Parses Retry-After header per RFC 7231 Section 7.1.3.
     * Can be either integer seconds or HTTP-date.
     */
    public function getRetryAfter(): ?int
    {
        $header = $this->getHeader('retry-after');
        if ($header === null) {
            return null;
        }

        $header = trim($header);
        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            $diff = $timestamp - time();
            return max(0, $diff);
        }

        return null;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransferStats(): array
    {
        return $this->transferStats;
    }
}

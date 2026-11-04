<?php

declare(strict_types=1);

namespace SitePortal\Core;

final class Response
{
    private int $statusCode;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;

        // Default security headers
        $this->headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];

        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $body = $encoded !== false ? $encoded : '{}';

        $headers['Content-Type'] = 'application/json; charset=UTF-8';

        return new self($body, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        return new self($html, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $text, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';

        return new self($text, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function xml(string $xml, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/xml; charset=UTF-8';

        return new self($xml, $statusCode, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                return $headerValue;
            }
        }
        return null;
    }

    public function withRequestId(string $requestId): self
    {
        $this->headers['X-Request-Id'] = $requestId;
        return $this;
    }

    public function withCorrelationId(string $correlationId): self
    {
        $this->headers['X-Correlation-Id'] = $correlationId;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }
}

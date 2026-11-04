<?php

declare(strict_types=1);

namespace SitePortal\Core;

final class Request
{
    private string $method;
    private string $uri;
    private string $path;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $queryParams;

    /** @var array<string, mixed> */
    private array $postData;

    private string $body;
    private string $requestId;
    private string $correlationId;
    private string $locale;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postData
     */
    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        array $queryParams = [],
        array $postData = [],
        string $body = '',
        ?string $requestId = null,
        ?string $locale = null,
        ?string $correlationId = null
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = $this->normalizePath($uri);
        $this->headers = $this->normalizeHeaders($headers);
        $this->queryParams = $queryParams;
        $this->postData = $postData;
        $this->body = $body;
        $this->requestId = $this->resolveRequestId($requestId);
        $this->correlationId = $this->resolveCorrelationId($correlationId);
        $this->locale = $locale ?? $this->resolveLocale();
    }

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?string $body = null
    ): self {
        $serverData = $server ?? $_SERVER;
        $getData = $get ?? $_GET;
        $postData = $post ?? $_POST;

        $method = isset($serverData['REQUEST_METHOD']) && is_string($serverData['REQUEST_METHOD'])
            ? $serverData['REQUEST_METHOD']
            : 'GET';

        $uri = isset($serverData['REQUEST_URI']) && is_string($serverData['REQUEST_URI'])
            ? $serverData['REQUEST_URI']
            : '/';

        $headers = [];
        foreach ($serverData as $key => $value) {
            if (is_string($value)) {
                if (str_starts_with($key, 'HTTP_')) {
                    $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$headerName] = $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $headerName = strtolower(str_replace('_', '-', $key));
                    $headers[$headerName] = $value;
                }
            }
        }

        $rawBody = $body ?? (string) file_get_contents('php://input');

        return new self($method, $uri, $headers, $getData, $postData, $rawBody);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $postData
     */
    public static function create(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $queryParams = [],
        array $postData = [],
        ?string $requestId = null,
        ?string $correlationId = null
    ): self {
        return new self($method, $uri, $headers, $queryParams, $postData, $body, $requestId, null, $correlationId);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /** @return array<string, mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getPostData(): array
    {
        return $this->postData;
    }

    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->postData[$key] ?? $default;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    private function normalizePath(string $uri): string
    {
        $parsed = parse_url($uri, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
        }

        return $normalized;
    }

    private function resolveRequestId(?string $requestId): string
    {
        if ($requestId !== null && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $requestId) === 1) {
            return $requestId;
        }

        $headerId = $this->getHeader('x-request-id');
        if ($headerId !== null && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $headerId) === 1) {
            return $headerId;
        }

        return 'req_' . bin2hex(random_bytes(16));
    }

    private function resolveCorrelationId(?string $correlationId): string
    {
        if ($correlationId !== null && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $correlationId) === 1) {
            return $correlationId;
        }

        $headerId = $this->getHeader('x-correlation-id');
        if ($headerId !== null && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $headerId) === 1) {
            return $headerId;
        }

        return 'corr_' . bin2hex(random_bytes(16));
    }

    private function resolveLocale(): string
    {
        $queryLang = $this->getQuery('lang');
        if (is_string($queryLang)) {
            $normalized = $this->normalizeLocaleString($queryLang);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $accept = $this->getHeader('accept-language');
        if ($accept !== null) {
            if (str_starts_with(strtolower($accept), 'en')) {
                return 'en-US';
            }
            if (str_starts_with(strtolower($accept), 'tr')) {
                return 'tr-TR';
            }
        }

        return 'tr-TR';
    }

    private function normalizeLocaleString(string $locale): ?string
    {
        $lower = strtolower($locale);
        if (in_array($lower, ['tr', 'tr-tr', 'tr_tr'], true)) {
            return 'tr-TR';
        }
        if (in_array($lower, ['en', 'en-us', 'en_us'], true)) {
            return 'en-US';
        }

        return null;
    }
}

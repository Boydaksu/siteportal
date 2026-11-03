<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

final class PinnedTarget
{
    private string $originalUrl;
    private string $normalizedUrl;
    private string $scheme;
    private string $host;
    private int $port;
    private string $path;
    private ?string $query;

    /** @var list<string> */
    private array $pinnedIps;

    private string $primaryIp;

    /**
     * @param list<string> $pinnedIps
     */
    public function __construct(
        string $originalUrl,
        string $normalizedUrl,
        string $scheme,
        string $host,
        int $port,
        string $path,
        ?string $query,
        array $pinnedIps
    ) {
        $this->originalUrl = $originalUrl;
        $this->normalizedUrl = $normalizedUrl;
        $this->scheme = strtolower($scheme);
        $this->host = strtolower($host);
        $this->port = $port;
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->pinnedIps = $pinnedIps;

        if ($this->pinnedIps === []) {
            throw new \InvalidArgumentException('Pinned target requires at least one validated IP address.');
        }

        $this->primaryIp = $this->pinnedIps[0];
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getNormalizedUrl(): string
    {
        return $this->normalizedUrl;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function getPinnedIps(): array
    {
        return $this->pinnedIps;
    }

    public function getPrimaryIp(): string
    {
        return $this->primaryIp;
    }

    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }

    /**
     * Generates a CURLOPT_RESOLVE formatted string: "host:port:ip".
     * This pins the DNS resolution for HTTP clients, completely mitigating DNS rebinding attacks.
     */
    public function getCurlResolveOption(?string $ip = null): string
    {
        $targetIp = $ip ?? $this->primaryIp;

        // If IPv6 literal, strip brackets if present
        $cleanIp = trim($targetIp, '[]');

        return sprintf('%s:%d:%s', $this->host, $this->port, $cleanIp);
    }
}

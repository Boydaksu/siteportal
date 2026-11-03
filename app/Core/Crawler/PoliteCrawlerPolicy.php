<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

use SitePortal\Core\Config;

final class PoliteCrawlerPolicy
{
    private string $botName;
    private string $botVersion;
    private string $userAgent;
    private int $connectTimeout;
    private int $timeout;
    private int $maxResponseBytes;
    private int $maxDecompressedBytes;
    private int $maxRedirects;
    private int $robotsCacheTtl;
    private bool $failClosedOnError;
    private float $defaultCrawlDelay;
    private float $maxCrawlDelay;
    private bool $respectRetryAfter;

    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $cfg = Config::getInstance();
        $this->botName = (string) ($overrides['bot_name'] ?? $cfg->get('crawler.bot_name', 'SitePortalBot'));
        $this->botVersion = (string) ($overrides['bot_version'] ?? $cfg->get('crawler.bot_version', '0.1.0'));
        $this->userAgent = (string) ($overrides['user_agent'] ?? $cfg->get(
            'crawler.user_agent',
            'SitePortalBot/0.1 (+https://github.com/Boydaksu/siteportal; bot@siteportal.local)'
        ));
        $this->connectTimeout = (int) ($overrides['connect_timeout'] ?? $cfg->get('crawler.connect_timeout', 5));
        $this->timeout = (int) ($overrides['timeout'] ?? $cfg->get('crawler.timeout', 12));
        $this->maxResponseBytes = (int) ($overrides['max_response_bytes'] ?? $cfg->get(
            'crawler.max_response_bytes',
            2 * 1024 * 1024
        ));
        $this->maxDecompressedBytes = (int) ($overrides['max_decompressed_bytes'] ?? $cfg->get(
            'crawler.max_decompressed_bytes',
            8 * 1024 * 1024
        ));
        $this->maxRedirects = (int) ($overrides['max_redirects'] ?? $cfg->get('crawler.max_redirects', 5));
        $this->robotsCacheTtl = (int) (
            $overrides['robots']['cache_ttl'] ?? $cfg->get('crawler.robots.cache_ttl', 3600)
        );
        $this->failClosedOnError = (bool) ($overrides['robots']['fail_closed_on_error'] ?? $cfg->get(
            'crawler.robots.fail_closed_on_error',
            true
        ));
        $this->defaultCrawlDelay = (float) ($overrides['robots']['default_crawl_delay'] ?? $cfg->get(
            'crawler.robots.default_crawl_delay',
            1.0
        ));
        $this->maxCrawlDelay = (float) ($overrides['robots']['max_crawl_delay'] ?? $cfg->get(
            'crawler.robots.max_crawl_delay',
            30.0
        ));
        $this->respectRetryAfter = (bool) ($overrides['respect_retry_after'] ?? $cfg->get(
            'crawler.respect_retry_after',
            true
        ));
    }

    public function getBotName(): string
    {
        return $this->botName;
    }

    public function getBotVersion(): string
    {
        return $this->botVersion;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getMaxResponseBytes(): int
    {
        return $this->maxResponseBytes;
    }

    public function getMaxDecompressedBytes(): int
    {
        return $this->maxDecompressedBytes;
    }

    public function getMaxRedirects(): int
    {
        return $this->maxRedirects;
    }

    public function getRobotsCacheTtl(): int
    {
        return $this->robotsCacheTtl;
    }

    public function isFailClosedOnError(): bool
    {
        return $this->failClosedOnError;
    }

    public function getDefaultCrawlDelay(): float
    {
        return $this->defaultCrawlDelay;
    }

    public function getMaxCrawlDelay(): float
    {
        return $this->maxCrawlDelay;
    }

    public function shouldRespectRetryAfter(): bool
    {
        return $this->respectRetryAfter;
    }
}

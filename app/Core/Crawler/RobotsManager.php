<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler;

use SitePortal\Core\Crawler\Exceptions\RobotsDisallowedException;
use SitePortal\Core\Crawler\Exceptions\RobotsUnavailableException;
use SitePortal\Core\Http\Exceptions\HttpException;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\Security\Exceptions\SecurityException;
use Throwable;

final class RobotsManager
{
    private HttpClientInterface $client;
    private RobotsCacheInterface $cache;
    private PoliteCrawlerPolicy $policy;

    public function __construct(
        ?HttpClientInterface $client = null,
        ?RobotsCacheInterface $cache = null,
        ?PoliteCrawlerPolicy $policy = null
    ) {
        $this->client = $client ?? new SafeHttpClient();
        $this->cache = $cache ?? new MemoryRobotsCache();
        $this->policy = $policy ?? new PoliteCrawlerPolicy();
    }

    /**
     * Resolves the RobotsParser for the origin of the target URL.
     * Uses cache if available; fetches via SafeHttpClient if not.
     *
     * @throws RobotsUnavailableException When robots.txt cannot be fetched and fail-closed is active.
     */
    public function getRobotsParserForUrl(string $url): RobotsParser
    {
        $origin = $this->extractOrigin($url);

        $cached = $this->cache->get($origin);
        if ($cached !== null) {
            return $cached;
        }

        $robotsUrl = $origin . '/robots.txt';

        try {
            $response = $this->client->get($robotsUrl, [
                'timeout' => min(5, $this->policy->getTimeout()),
                'max_response_bytes' => 512 * 1024, // robots.txt is at most 500KB per RFC 9309
            ]);
        } catch (Throwable $e) {
            if ($this->policy->isFailClosedOnError()) {
                throw new RobotsUnavailableException(
                    $origin,
                    0,
                    'Failed to fetch robots.txt from ' . $origin . ': ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            // Fallback: assume empty
            $parser = new RobotsParser('');
            $this->cache->set($origin, $parser, 60);
            return $parser;
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            $parser = new RobotsParser($response->getBody());
            $this->cache->set($origin, $parser, $this->policy->getRobotsCacheTtl());
            return $parser;
        }

        // RFC 9309 Section 2.3.1.2: 4xx errors mean no robots.txt exists, grant access
        if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
            $parser = new RobotsParser('');
            $this->cache->set($origin, $parser, $this->policy->getRobotsCacheTtl());
            return $parser;
        }

        // 5xx errors or 429 rate limit per RFC 9309 Section 2.3.1.3: temporary server error, fail-closed
        if ($this->policy->isFailClosedOnError()) {
            throw new RobotsUnavailableException(
                $origin,
                $statusCode,
                'Robots.txt request returned HTTP ' . $statusCode . ' for origin ' . $origin
            );
        }

        $parser = new RobotsParser('');
        $this->cache->set($origin, $parser, 60);
        return $parser;
    }

    /**
     * Checks if crawling the target URL is permitted by robots.txt rules.
     */
    public function isAllowed(string $url, ?string $userAgent = null): bool
    {
        $ua = $userAgent ?? $this->policy->getBotName();
        $parser = $this->getRobotsParserForUrl($url);

        return $parser->isAllowed($url, $ua);
    }

    /**
     * Asserts that crawling the target URL is permitted.
     * Throws RobotsDisallowedException if disallowed.
     *
     * @throws RobotsDisallowedException
     * @throws RobotsUnavailableException
     */
    public function assertCanCrawl(string $url, ?string $userAgent = null): void
    {
        $ua = $userAgent ?? $this->policy->getBotName();
        $parser = $this->getRobotsParserForUrl($url);

        if (!$parser->isAllowed($url, $ua)) {
            throw new RobotsDisallowedException($url);
        }
    }

    /**
     * Returns the crawl delay specified for the given URL's origin, capped by policy.
     */
    public function getCrawlDelay(string $url, ?string $userAgent = null): ?float
    {
        $ua = $userAgent ?? $this->policy->getBotName();
        $parser = $this->getRobotsParserForUrl($url);
        $delay = $parser->getCrawlDelay($ua);

        if ($delay === null) {
            return null;
        }

        return min($delay, $this->policy->getMaxCrawlDelay());
    }

    /**
     * Returns sitemaps advertised in robots.txt.
     *
     * @return list<string>
     */
    public function getSitemaps(string $url): array
    {
        $parser = $this->getRobotsParserForUrl($url);
        return $parser->getSitemaps();
    }

    private function extractOrigin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = strtolower($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }
}

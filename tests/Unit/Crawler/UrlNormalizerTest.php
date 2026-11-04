<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;

final class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new UrlNormalizer(stripTrackingParams: true, normalizeTrailingSlash: false);
    }

    public function testStripsFragmentReliably(): void
    {
        $input = 'https://example.com/path#section-1';
        $expected = 'https://example.com/path';
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function testNormalizesSchemeAndHostToLowercase(): void
    {
        $input = 'HTTP://WWW.EXAMPLE.COM/SubPage';
        $expected = 'http://www.example.com/SubPage';
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function testRemovesStandardPorts(): void
    {
        $this->assertSame('http://example.com/page', $this->normalizer->normalize('http://example.com:80/page'));
        $this->assertSame('https://example.com/page', $this->normalizer->normalize('https://example.com:443/page'));

        // Non-standard port must be retained
        $this->assertSame('http://example.com:8080/page', $this->normalizer->normalize('http://example.com:8080/page'));
    }

    public function testResolvesDotSegmentsPerRfc3986(): void
    {
        $this->assertSame(
            'https://example.com/c/d',
            $this->normalizer->normalize('https://example.com/a/b/../../c/./d')
        );
        $this->assertSame(
            'https://example.com/foo/',
            $this->normalizer->normalize('https://example.com/foo/bar/..')
        );
    }

    public function testCollapsesDuplicateConsecutiveSlashesInPath(): void
    {
        $this->assertSame(
            'https://example.com/path/to/resource',
            $this->normalizer->normalize('https://example.com/path//to///resource')
        );
    }

    public function testSortsQueryParametersAlphabetically(): void
    {
        $input = 'https://example.com/search?z=last&a=first&m=middle';
        $expected = 'https://example.com/search?a=first&m=middle&z=last';
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function testPreservesDifferentMeaningfulQueryParameters(): void
    {
        // Criterion: Farklı anlamlı query parametreleri korunur
        $url1 = $this->normalizer->normalize('https://example.com/shop?category=shoes&page=1');
        $url2 = $this->normalizer->normalize('https://example.com/shop?category=shoes&page=2');
        $url3 = $this->normalizer->normalize('https://example.com/shop?category=hats&page=1');

        $this->assertSame('https://example.com/shop?category=shoes&page=1', $url1);
        $this->assertSame('https://example.com/shop?category=shoes&page=2', $url2);
        $this->assertSame('https://example.com/shop?category=hats&page=1', $url3);

        $this->assertNotSame($url1, $url2);
        $this->assertNotSame($url1, $url3);

        $this->assertNotSame(
            $this->normalizer->hash($url1),
            $this->normalizer->hash($url2)
        );
    }

    public function testStripsKnownTrackingParametersByDefault(): void
    {
        $input = 'https://example.com/landing?utm_source=google&utm_medium=cpc&content=guide&fbclid=12345';
        $expected = 'https://example.com/landing?content=guide';
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function testCanDisableTrackingParamStripping(): void
    {
        $strict = new UrlNormalizer(stripTrackingParams: false);
        $input = 'https://example.com/landing?utm_source=google&page=2';
        $this->assertSame('https://example.com/landing?page=2&utm_source=google', $strict->normalize($input));
    }

    public function testResolvesRelativeUrlsCorrectly(): void
    {
        $base = 'https://example.com/blog/2026/';

        // Absolute path
        $this->assertSame(
            'https://example.com/about',
            $this->normalizer->normalize('/about', $base)
        );

        // Relative subpath
        $this->assertSame(
            'https://example.com/blog/2026/post-1',
            $this->normalizer->normalize('post-1', $base)
        );

        // Parent traversal
        $this->assertSame(
            'https://example.com/blog/index',
            $this->normalizer->normalize('../index', $base)
        );

        // Query only relative
        $this->assertSame(
            'https://example.com/blog/2026/?page=2',
            $this->normalizer->normalize('?page=2', $base)
        );

        // Protocol-relative
        $this->assertSame(
            'https://cdn.example.com/app.js',
            $this->normalizer->normalize('//cdn.example.com/app.js', $base)
        );
    }

    public function testTrailingSlashCanonicalizationWhenConfigured(): void
    {
        $normalizerWithTrailingSlash = new UrlNormalizer(stripTrackingParams: true, normalizeTrailingSlash: true);

        $this->assertSame(
            'https://example.com/about',
            $normalizerWithTrailingSlash->normalize('https://example.com/about/')
        );

        // Root path preserves slash
        $this->assertSame(
            'https://example.com/',
            $normalizerWithTrailingSlash->normalize('https://example.com/')
        );
    }

    public function testUrlHashIsDeterministicSha256(): void
    {
        $url1 = 'https://example.com/page?b=2&a=1#section';
        $url2 = 'HTTPS://EXAMPLE.COM/page?a=1&b=2';

        $hash1 = $this->normalizer->hash($url1);
        $hash2 = $this->normalizer->hash($url2);

        $this->assertSame(64, strlen($hash1));
        $this->assertSame($hash1, $hash2);
        $this->assertTrue($this->normalizer->areEquivalent($url1, $url2));
    }
}

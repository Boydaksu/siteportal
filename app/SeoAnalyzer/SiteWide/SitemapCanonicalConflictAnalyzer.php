<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Detects conflicts between sitemap URLs, canonical tags, noindex directives, and HTTP status codes.
 * Rule: TECH_SITEMAP_URL_CONFLICT
 */
final class SitemapCanonicalConflictAnalyzer
{
    private UrlNormalizer $normalizer;

    public function __construct(?UrlNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new UrlNormalizer();
    }

    /**
     * @return list<Finding>
     */
    public function analyze(SiteCrawlContext $context): array
    {
        if (!$context->hasSitemap()) {
            return [
                new Finding(
                    ruleId: 'TECH_SITEMAP_URL_CONFLICT',
                    ruleVersion: '1.0',
                    status: FindingStatus::NOT_APPLICABLE,
                    severity: FindingSeverity::MEDIUM,
                    confidence: Confidence::HIGH,
                    dataSource: 'sitemap_and_html',
                    evidence: [['reason' => 'no_sitemap_discovered']],
                    messageKey: 'msg_sitemap_url_conflict_na'
                ),
            ];
        }

        /** @var list<array<string, mixed>> $conflicts */
        $conflicts = [];
        /** @var list<string> $affectedUrls */
        $affectedUrls = [];
        $hasHardFailure = false;

        foreach ($context->getSnapshots() as $url => $snapshot) {
            $normalizedUrl = $this->normalizer->normalize($url);
            if (!$context->isInSitemap($url) && !$context->isInSitemap($normalizedUrl)) {
                continue;
            }

            $httpResponse = $snapshot->getHttpResponse();
            $statusCode = $httpResponse?->getStatusCode() ?? 200;
            $parsed = $snapshot->getParsedDocument();

            // 1. Check HTTP Status (Sitemap URL must return 200 OK)
            if ($statusCode >= 300) {
                $hasHardFailure = true;
                $conflicts[] = [
                    'url' => $url,
                    'conflict_type' => 'status_not_200',
                    'status_code' => $statusCode,
                    'details' => "Sitemap URL returned HTTP {$statusCode} instead of 200 OK.",
                ];
                if (!in_array($url, $affectedUrls, true)) {
                    $affectedUrls[] = $url;
                }
            }

            if ($parsed !== null) {
                // 2. Check Noindex Directive
                if ($parsed->isNoindex()) {
                    $hasHardFailure = true;
                    $conflicts[] = [
                        'url' => $url,
                        'conflict_type' => 'noindex_conflict',
                        'details' => 'Sitemap URL contains a noindex directive (meta or header).',
                    ];
                    if (!in_array($url, $affectedUrls, true)) {
                        $affectedUrls[] = $url;
                    }
                }

                // 3. Check Canonical Mismatch (Non-self canonical)
                $canonical = $parsed->getCanonical();
                if ($canonical !== null && $canonical !== '') {
                    $normalizedCanonical = $this->normalizer->normalize($canonical, $url);
                    if ($normalizedCanonical !== '' && $normalizedCanonical !== $normalizedUrl) {
                        $conflicts[] = [
                            'url' => $url,
                            'conflict_type' => 'canonical_mismatch',
                            'canonical_target' => $normalizedCanonical,
                            'details' => 'Sitemap URL points to a different URL via rel="canonical".',
                        ];
                        if (!in_array($url, $affectedUrls, true)) {
                            $affectedUrls[] = $url;
                        }
                    }
                }
            }
        }

        if (!empty($conflicts)) {
            return [
                new Finding(
                    ruleId: 'TECH_SITEMAP_URL_CONFLICT',
                    ruleVersion: '1.0',
                    status: $hasHardFailure ? FindingStatus::FAIL : FindingStatus::WARN,
                    severity: FindingSeverity::MEDIUM,
                    confidence: Confidence::HIGH,
                    dataSource: 'sitemap_and_html',
                    evidence: $conflicts,
                    affectedUrls: $affectedUrls,
                    fixKey: 'fix_tech_sitemap_url_conflict',
                    messageKey: 'msg_tech_sitemap_url_conflict'
                ),
            ];
        }

        return [
            new Finding(
                ruleId: 'TECH_SITEMAP_URL_CONFLICT',
                ruleVersion: '1.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'sitemap_and_html',
                evidence: [['sitemap_urls_checked' => count($context->getSitemapUrls())]],
                messageKey: 'msg_tech_sitemap_url_conflict_pass'
            ),
        ];
    }
}

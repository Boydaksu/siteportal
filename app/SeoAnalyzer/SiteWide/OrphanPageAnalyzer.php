<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\SiteWide;

use SitePortal\Core\Crawler\Normalizer\UrlNormalizer;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;

/**
 * Analyzes orphan pages across the link graph and sitemap.
 *
 * CRITICAL ACCEPTANCE CRITERIA:
 * In case of partial or sampled crawl (budget or depth cutoff), orphan pages
 * MUST NOT be marked as confirmed/definitive orphans (LINK_ORPHAN_SUSPECT with low confidence).
 */
final class OrphanPageAnalyzer
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
        $seedUrl = $this->normalizer->normalize($context->getSeedUrl());
        $linkGraph = $context->getLinkGraph();
        $isPartial = $context->isPartialCrawl();

        /** @var list<string> $candidateUrls */
        $candidateUrls = [];

        // Candidates: URLs from sitemap and crawled URLs
        foreach ($context->getSitemapUrls() as $sitemapUrl) {
            $norm = $this->normalizer->normalize($sitemapUrl);
            if ($norm !== '' && $norm !== $seedUrl && !in_array($norm, $candidateUrls, true)) {
                $candidateUrls[] = $norm;
            }
        }

        foreach (array_keys($context->getSnapshots()) as $crawledUrl) {
            $norm = $this->normalizer->normalize($crawledUrl);
            if ($norm !== '' && $norm !== $seedUrl && !in_array($norm, $candidateUrls, true)) {
                $candidateUrls[] = $norm;
            }
        }

        /** @var list<array<string, mixed>> $orphanList */
        $orphanList = [];
        /** @var list<string> $affectedUrls */
        $affectedUrls = [];

        foreach ($candidateUrls as $candidateUrl) {
            $inlinks = $linkGraph->getInlinks($candidateUrl);
            $internalInlinks = array_filter($inlinks, fn ($edge) => $edge->isInternal());

            if (empty($internalInlinks)) {
                $affectedUrls[] = $candidateUrl;

                if ($isPartial) {
                    // ACCEPTANCE CRITERION: Under incomplete/sampled crawl, do NOT mark as confirmed orphan
                    $orphanList[] = [
                        'url' => $candidateUrl,
                        'is_confirmed_orphan' => false,
                        'status' => 'suspected_orphan',
                        'inlinks_count' => 0,
                        'in_sitemap' => $context->isInSitemap($candidateUrl),
                        'sampling_warning' => 'Crawl was sampled or partial; un-crawled pages might link to this URL.',
                    ];
                } else {
                    // Complete crawl: confirmed orphan
                    $orphanList[] = [
                        'url' => $candidateUrl,
                        'is_confirmed_orphan' => true,
                        'status' => 'confirmed_orphan',
                        'inlinks_count' => 0,
                        'in_sitemap' => $context->isInSitemap($candidateUrl),
                        'details' => 'Full crawl completed; no internal incoming links found from any site page.',
                    ];
                }
            }
        }

        if (!empty($orphanList)) {
            return [
                new Finding(
                    ruleId: 'LINK_ORPHAN_SUSPECT',
                    ruleVersion: '1.0',
                    status: FindingStatus::WARN,
                    severity: FindingSeverity::LOW,
                    confidence: $isPartial ? Confidence::LOW : Confidence::HIGH,
                    dataSource: 'link_graph_and_sitemap',
                    evidence: $orphanList,
                    affectedUrls: $affectedUrls,
                    fixKey: 'fix_link_orphan_suspect',
                    messageKey: $isPartial
                        ? 'msg_link_orphan_suspect_sampled'
                        : 'msg_link_orphan_confirmed'
                ),
            ];
        }

        return [
            new Finding(
                ruleId: 'LINK_ORPHAN_SUSPECT',
                ruleVersion: '1.0',
                status: FindingStatus::PASS,
                severity: FindingSeverity::LOW,
                confidence: $isPartial ? Confidence::MEDIUM : Confidence::HIGH,
                dataSource: 'link_graph_and_sitemap',
                evidence: [['total_candidates_checked' => count($candidateUrls)]],
                messageKey: 'msg_link_orphan_suspect_pass'
            ),
        ];
    }
}

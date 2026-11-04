<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * @phpstan-import-type LinkItem from \SitePortal\Core\Parser\ParsedDocument
 */
final class RelNofollowRule implements RuleInterface
{
    public const string RULE_ID = 'LINK_REL_NOFOLLOW';
    public const string RULE_VERSION = '1.0';

    public function getId(): string
    {
        return self::RULE_ID;
    }

    public function getVersion(): string
    {
        return self::RULE_VERSION;
    }

    public function getCategory(): string
    {
        return 'links';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::LOW;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $links = $doc->getLinks();
        if (empty($links)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No links found in document; rel=nofollow check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $internalLinks = $doc->getInternalLinks();
        $externalLinks = $doc->getExternalLinks();

        /** @var list<LinkItem> $internalNofollow */
        $internalNofollow = [];
        foreach ($internalLinks as $link) {
            if ($link['is_nofollow']) {
                $internalNofollow[] = $link;
            }
        }

        /** @var list<LinkItem> $externalNofollow */
        $externalNofollow = [];
        foreach ($externalLinks as $link) {
            if ($link['is_nofollow']) {
                $externalNofollow[] = $link;
            }
        }

        if (!empty($internalNofollow)) {
            $samples = array_map(
                static fn(array $l): array => [
                    'href' => $l['href'],
                    'resolved_url' => $l['resolved_url'],
                    'rel' => $l['rel'],
                ],
                array_slice($internalNofollow, 0, 10)
            );

            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'internal_nofollow_count' => count($internalNofollow),
                    'external_nofollow_count' => count($externalNofollow),
                    'total_internal_links' => count($internalLinks),
                    'total_external_links' => count($externalLinks),
                    'samples' => $samples,
                    'warning' => 'Internal links should typically not use rel="nofollow" as it blocks PageRank flow',
                ]],
                affectedUrls: array_map(static fn(array $l): string => $l['resolved_url'], $internalNofollow),
                fixKey: 'rules.LINK_REL_NOFOLLOW.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [[
                'internal_nofollow_count' => 0,
                'external_nofollow_count' => count($externalNofollow),
                'total_internal_links' => count($internalLinks),
                'total_external_links' => count($externalLinks),
                'message' => 'No internal links have rel="nofollow" attribute',
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

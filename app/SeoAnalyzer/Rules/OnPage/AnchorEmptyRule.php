<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class AnchorEmptyRule implements RuleInterface
{
    public const string RULE_ID = 'LINK_ANCHOR_EMPTY';
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
                evidence: [['note' => 'No links found in document; empty anchor check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $emptyAnchorLinks = [];
        foreach ($links as $link) {
            $text = trim($link['text']);
            $title = trim((string) ($link['title'] ?? ''));

            if ($text === '' && $title === '') {
                $emptyAnchorLinks[] = [
                    'href' => $link['href'],
                    'resolved_url' => $link['resolved_url'],
                ];
            }
        }

        if (!empty($emptyAnchorLinks)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'empty_count' => count($emptyAnchorLinks),
                    'total_links' => count($links),
                    'empty_links' => array_slice($emptyAnchorLinks, 0, 10),
                    'warning' => 'Links without anchor text or title reduce accessibility '
                        . 'and keyword relevance for search crawlers',
                ]],
                affectedUrls: array_map(static fn(array $l): string => $l['resolved_url'], $emptyAnchorLinks),
                fixKey: 'rules.LINK_ANCHOR_EMPTY.fix'
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
                'total_links' => count($links),
                'empty_count' => 0,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

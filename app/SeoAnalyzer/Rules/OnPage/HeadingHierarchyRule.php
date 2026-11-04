<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class HeadingHierarchyRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_HEADING_HIERARCHY';
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
        return 'onpage';
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

        $headings = $doc->getHeadings();
        if (empty($headings)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No headings found in document; hierarchy check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $gaps = [];
        $prevLevel = 0;

        foreach ($headings as $h) {
            $currLevel = $h['level'];
            // Detect hierarchy level jumps: e.g. from H1 to H3 skipping H2
            if ($prevLevel > 0 && $currLevel > ($prevLevel + 1)) {
                $gaps[] = [
                    'from_tag' => "h{$prevLevel}",
                    'to_tag' => "h{$currLevel}",
                    'text' => $h['text'],
                    'index' => $h['index'],
                ];
            }
            $prevLevel = $currLevel;
        }

        if (!empty($gaps)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::MEDIUM,
                dataSource: 'parser',
                evidence: [[
                    'has_level_skips' => true,
                    'gap_count' => count($gaps),
                    'gaps' => $gaps,
                    'warning' => 'Headings skip hierarchy levels (e.g. H1 directly to H3 without an intervening H2)',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_HEADING_HIERARCHY.fix'
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
                'has_level_skips' => false,
                'total_headings' => count($headings),
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

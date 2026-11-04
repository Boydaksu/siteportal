<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class MetaDescriptionMissingRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_META_DESC_MISSING';
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

        $desc = $doc->getMetaDescription();
        $isMissing = $desc === null || trim($desc) === '';

        if ($isMissing) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'has_meta_description' => false,
                    'note' => 'No meta description tag found; search engines can generate snippets from '
                        . 'body text, but a concise description improves click-through rates',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_META_DESC_MISSING.fix'
            );
        }

        $trimmed = trim($desc);

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [[
                'has_meta_description' => true,
                'char_count' => mb_strlen($trimmed, 'UTF-8'),
                'description' => $trimmed,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

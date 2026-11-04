<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class TitleTruncationRiskRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_TITLE_TRUNCATION_RISK';
    public const string RULE_VERSION = '1.0';

    /**
     * Estimated pixel width threshold where Google SERP typically truncates snippet titles (~580-600px).
     * Character heuristic is ~60 characters for standard Latin/Turkish typography.
     */
    public const int CHAR_THRESHOLD = 60;

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

        $title = $doc->getTitle();
        if ($title === null || trim($title) === '') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No title present; truncation check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $trimmed = trim($title);
        $charCount = mb_strlen($trimmed, 'UTF-8');
        $isTruncationRisk = $charCount > self::CHAR_THRESHOLD;

        if ($isTruncationRisk) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::MEDIUM,
                dataSource: 'parser',
                evidence: [[
                    'title' => $trimmed,
                    'char_count' => $charCount,
                    'threshold' => self::CHAR_THRESHOLD,
                    'truncation_risk' => true,
                    'note' => 'Title exceeds ~60 characters and may be truncated in search snippets; '
                        . 'this is a display warning, not a ranking penalty',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_TITLE_TRUNCATION_RISK.fix'
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
                'title' => $trimmed,
                'char_count' => $charCount,
                'truncation_risk' => false,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

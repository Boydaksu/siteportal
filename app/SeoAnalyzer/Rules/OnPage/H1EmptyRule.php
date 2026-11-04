<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class H1EmptyRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_H1_EMPTY';
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

        $h1Texts = $doc->getH1Texts();
        $nonEmptyH1s = array_values(array_filter($h1Texts, static fn(string $t): bool => trim($t) !== ''));

        if (empty($nonEmptyH1s)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'h1_count' => count($h1Texts),
                    'has_h1' => false,
                    'warning' => 'Page has no primary H1 heading; an H1 heading clarifies the main topic '
                        . 'for visitors and search engines',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_H1_EMPTY.fix'
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
                'h1_count' => count($nonEmptyH1s),
                'h1_texts' => $nonEmptyH1s,
                'multiple_h1_valid' => count($nonEmptyH1s) > 1,
                'note' => 'Having one or multiple semantic H1 headings is valid HTML5 and accepted by search engines',
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

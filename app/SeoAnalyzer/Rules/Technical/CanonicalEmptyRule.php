<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class CanonicalEmptyRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_CANONICAL_EMPTY';
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
        return 'technical';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::MEDIUM;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available to check canonical validity']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $canonical = $doc->getCanonical();

        if ($canonical === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'declared' => false,
                    'note' => 'No canonical tag present; handled by TECH_CANONICAL_MISSING',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $trimmed = trim($canonical);
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        $isValidUrl = $trimmed !== '' && ($scheme === 'http' || $scheme === 'https');

        if (!$isValidUrl) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'declared' => true,
                    'raw_canonical' => $canonical,
                    'is_empty_or_invalid' => true,
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_CANONICAL_EMPTY.fix'
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
                'declared' => true,
                'canonical' => $trimmed,
                'is_valid' => true,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

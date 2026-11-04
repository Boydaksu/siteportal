<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class CanonicalOutOfScopeRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_CANONICAL_OUT_OF_SCOPE';
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
        $canonical = $doc?->getCanonical();

        if ($canonical === null || trim($canonical) === '') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No canonical URL declared to check domain scope']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $pageHost = (string) parse_url($snapshot->getFinalUrl(), PHP_URL_HOST);
        $canonicalHost = (string) parse_url(trim($canonical), PHP_URL_HOST);

        $isCrossDomain = $canonicalHost !== '' && strtolower($pageHost) !== strtolower($canonicalHost);

        if ($isCrossDomain) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'page_host' => $pageHost,
                    'canonical_host' => $canonicalHost,
                    'canonical_url' => trim($canonical),
                    'is_cross_domain' => true,
                    'note' => 'Cross-domain canonical points to another domain; verify intentional syndication.',
                ]],
                affectedUrls: [$snapshot->getFinalUrl(), trim($canonical)],
                fixKey: 'rules.TECH_CANONICAL_OUT_OF_SCOPE.fix'
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
                'page_host' => $pageHost,
                'canonical_host' => $canonicalHost,
                'is_cross_domain' => false,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

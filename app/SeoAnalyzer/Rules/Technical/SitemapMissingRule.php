<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class SitemapMissingRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_SITEMAP_MISSING';
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
        return FindingSeverity::INFO;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $discovery = $snapshot->getSitemapDiscovery();
        if ($discovery === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::INFO,
                confidence: Confidence::UNKNOWN,
                dataSource: 'sitemap',
                evidence: [['reason' => 'Sitemap discovery not performed or unavailable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $discovered = $discovery['discovered'];
        $urls = $discovery['urls'];

        if (!$discovered || empty($urls)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'sitemap',
                evidence: [[
                    'discovered' => false,
                    'note' => 'No sitemap discovered; optional for smaller sites with clear navigation',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_SITEMAP_MISSING.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'sitemap',
            evidence: [[
                'discovered' => true,
                'sitemap_urls' => $urls,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

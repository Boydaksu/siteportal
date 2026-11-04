<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class SitemapStatusRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_SITEMAP_STATUS';
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
        $discovery = $snapshot->getSitemapDiscovery();
        if ($discovery === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::UNKNOWN,
                dataSource: 'sitemap',
                evidence: [['reason' => 'Sitemap discovery not performed']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        if (!$discovery['discovered'] || empty($discovery['urls'])) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'sitemap',
                evidence: [['note' => 'No sitemap discovered; status check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $statusCodes = $discovery['status_codes'] ?? [];
        $failedSitemaps = [];

        foreach ($statusCodes as $url => $code) {
            if ($code >= 400) {
                $failedSitemaps[$url] = $code;
            }
        }

        if (!empty($failedSitemaps)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'sitemap',
                evidence: [[
                    'has_error' => true,
                    'status_codes' => $statusCodes,
                    'failed_sitemaps' => $failedSitemaps,
                    'error' => 'One or more sitemaps returned an HTTP 4xx or 5xx error status',
                ]],
                affectedUrls: array_keys($failedSitemaps),
                fixKey: 'rules.TECH_SITEMAP_STATUS.fix'
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
                'has_error' => false,
                'status_codes' => $statusCodes,
                'all_accessible' => true,
            ]],
            affectedUrls: array_keys($statusCodes)
        );
    }
}

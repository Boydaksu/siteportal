<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class RobotsUnavailableRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_ROBOTS_UNAVAILABLE';
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
        $policy = $snapshot->getRobotsPolicy();
        if ($policy === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::UNKNOWN,
                dataSource: 'robots',
                evidence: [['reason' => 'Robots fetch status not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $statusCode = $policy['status_code'] ?? null;

        // If robots.txt returned 5xx server error, crawler fails closed (Google halts crawling)
        if ($statusCode !== null && $statusCode >= 500 && $statusCode < 600) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'robots',
                evidence: [[
                    'robots_status_code' => $statusCode,
                    'error' => 'robots.txt server error (5xx); search engines pause crawling when unreachable',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_ROBOTS_UNAVAILABLE.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'robots',
            evidence: [[
                'robots_status_code' => $statusCode ?? 200,
                'available' => true,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

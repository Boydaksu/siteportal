<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class RobotsDisallowRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_ROBOTS_DISALLOW';
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
        return FindingSeverity::HIGH;
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
                evidence: [['reason' => 'Robots policy evaluation data not provided']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $allowed = $policy['allowed'];
        $reason = $policy['reason'] ?? ($allowed ? 'Allowed by robots.txt' : 'Disallowed by robots.txt');

        if (!$allowed) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'robots',
                evidence: [[
                    'allowed' => false,
                    'reason' => $reason,
                    'final_url' => $snapshot->getFinalUrl(),
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_ROBOTS_DISALLOW.fix'
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
                'allowed' => true,
                'reason' => $reason,
                'final_url' => $snapshot->getFinalUrl(),
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

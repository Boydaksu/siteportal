<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class HttpsDowngradeRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_HTTPS_DOWNGRADE';
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
        $reqScheme = strtolower((string) parse_url($snapshot->getRequestedUrl(), PHP_URL_SCHEME));
        if ($reqScheme !== 'https') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'requested_scheme' => $reqScheme,
                    'note' => 'Initial URL was not HTTPS, downgrade check not applicable',
                ]],
                affectedUrls: [$snapshot->getRequestedUrl()]
            );
        }

        $downgradedHops = [];
        foreach ($snapshot->getRedirectHistory() as $hop) {
            $scheme = strtolower((string) parse_url($hop['url'], PHP_URL_SCHEME));
            if ($scheme === 'http') {
                $downgradedHops[] = $hop;
            }
        }

        $finalScheme = strtolower((string) parse_url($snapshot->getFinalUrl(), PHP_URL_SCHEME));
        $hasFinalDowngrade = $finalScheme === 'http';

        if (!empty($downgradedHops) || $hasFinalDowngrade) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'requested_url' => $snapshot->getRequestedUrl(),
                    'final_url' => $snapshot->getFinalUrl(),
                    'downgraded_hops' => $downgradedHops,
                    'final_downgraded' => $hasFinalDowngrade,
                ]],
                affectedUrls: [$snapshot->getRequestedUrl(), $snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_HTTPS_DOWNGRADE.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'http',
            evidence: [[
                'secure_preserved' => true,
                'final_url' => $snapshot->getFinalUrl(),
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

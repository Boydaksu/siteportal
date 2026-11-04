<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class HttpsRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_HTTPS';
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
        $finalUrl = $snapshot->getFinalUrl();
        $scheme = (string) parse_url($finalUrl, PHP_URL_SCHEME);
        $isHttps = strtolower($scheme) === 'https';

        $mixed = $snapshot->getMixedContent();
        $hasMixedContent = $mixed['has_mixed_content'] ?? false;
        $insecureResources = $mixed['insecure_resources'] ?? [];

        if (!$isHttps) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'final_url' => $finalUrl,
                    'scheme' => $scheme,
                    'is_https' => false,
                ]],
                affectedUrls: [$finalUrl],
                fixKey: 'rules.TECH_HTTPS.fix'
            );
        }

        if ($hasMixedContent) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'final_url' => $finalUrl,
                    'scheme' => 'https',
                    'has_mixed_content' => true,
                    'insecure_resources' => $insecureResources,
                ]],
                affectedUrls: [$finalUrl],
                fixKey: 'rules.TECH_HTTPS.fix'
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
                'final_url' => $finalUrl,
                'scheme' => 'https',
                'has_mixed_content' => false,
            ]],
            affectedUrls: [$finalUrl]
        );
    }
}

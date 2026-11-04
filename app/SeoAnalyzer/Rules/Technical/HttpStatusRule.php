<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class HttpStatusRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_HTTP_STATUS';
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
        $response = $snapshot->getHttpResponse();
        if ($response === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::UNKNOWN,
                dataSource: 'http',
                evidence: [['reason' => 'No HTTP response recorded for URL']],
                affectedUrls: [$snapshot->getRequestedUrl()],
                fixKey: 'rules.TECH_HTTP_STATUS.fix'
            );
        }

        $code = $response->getStatusCode();
        $finalUrl = $snapshot->getFinalUrl();

        if ($code >= 200 && $code < 300) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'status_code' => $code,
                    'effective_url' => $finalUrl,
                    'success' => true,
                ]],
                affectedUrls: [$finalUrl]
            );
        }

        if ($code >= 300 && $code < 400) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'status_code' => $code,
                    'effective_url' => $finalUrl,
                    'redirect' => true,
                ]],
                affectedUrls: [$snapshot->getRequestedUrl(), $finalUrl],
                fixKey: 'rules.TECH_HTTP_STATUS.fix'
            );
        }

        $severity = $code >= 500 ? FindingSeverity::CRITICAL : FindingSeverity::HIGH;

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::FAIL,
            severity: $severity,
            confidence: Confidence::HIGH,
            dataSource: 'http',
            evidence: [[
                'status_code' => $code,
                'effective_url' => $finalUrl,
                'is_error' => true,
            ]],
            affectedUrls: [$finalUrl],
            fixKey: 'rules.TECH_HTTP_STATUS.fix'
        );
    }
}

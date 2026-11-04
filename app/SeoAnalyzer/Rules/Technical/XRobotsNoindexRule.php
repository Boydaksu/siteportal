<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class XRobotsNoindexRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_X_ROBOTS_NOINDEX';
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
                evidence: [['reason' => 'No HTTP response headers available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $headerValues = $response->getHeaderValues('X-Robots-Tag');
        $hasNoindex = false;
        $matchedDirectives = [];

        foreach ($headerValues as $val) {
            $parts = array_map('trim', explode(',', strtolower($val)));
            foreach ($parts as $part) {
                $matchedDirectives[] = $part;
                if ($part === 'noindex' || $part === 'none') {
                    $hasNoindex = true;
                }
            }
        }

        if ($hasNoindex) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'x_robots_tag_headers' => $headerValues,
                    'parsed_directives' => $matchedDirectives,
                    'has_noindex' => true,
                    'warning' => 'HTTP X-Robots-Tag header forbids indexing',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_X_ROBOTS_NOINDEX.fix'
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
                'x_robots_tag_headers' => $headerValues,
                'has_noindex' => false,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

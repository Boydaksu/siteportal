<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class CharsetRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_CHARSET';
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
        return 'onpage';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::MEDIUM;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $doc = $snapshot->getParsedDocument();
        $htmlCharset = $doc?->getCharset();

        $response = $snapshot->getHttpResponse();
        $httpContentType = $response?->getHeader('content-type');
        $httpCharset = null;
        if ($httpContentType !== null && preg_match('/charset=([a-zA-Z0-9_\-]+)/i', $httpContentType, $m)) {
            $httpCharset = strtoupper(trim($m[1]));
        }

        if ($htmlCharset === null && $httpCharset === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'has_charset' => false,
                    'warning' => 'No character encoding declared via HTTP header or HTML meta tag; '
                        . 'UTF-8 declaration is recommended',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_CHARSET.fix'
            );
        }

        $effectiveCharset = $htmlCharset ?? $httpCharset;
        $isConflict = false;

        if ($htmlCharset !== null && $httpCharset !== null) {
            $isConflict = strtoupper($htmlCharset) !== strtoupper($httpCharset);
        }

        if ($isConflict) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'html_charset' => $htmlCharset,
                    'http_charset' => $httpCharset,
                    'is_conflict' => true,
                    'warning' => 'Charset conflict between HTTP header and HTML meta tag '
                        . 'may cause character decoding errors',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_CHARSET.fix'
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
                'effective_charset' => $effectiveCharset,
                'is_conflict' => false,
                'is_utf8' => strtoupper((string) $effectiveCharset) === 'UTF-8',
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

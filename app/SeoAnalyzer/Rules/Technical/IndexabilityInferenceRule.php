<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class IndexabilityInferenceRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_INDEXABILITY_INFERENCE';
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
        $blockers = [];

        // 1. HTTP status check
        $response = $snapshot->getHttpResponse();
        if ($response === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::INFO,
                confidence: Confidence::UNKNOWN,
                dataSource: 'crawler',
                evidence: [['reason' => 'No HTTP response available for indexability inference']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300) {
            $blockers[] = "HTTP status code {$statusCode} (non-2xx response)";
        }

        // 2. Robots.txt policy
        $robotsPolicy = $snapshot->getRobotsPolicy();
        if ($robotsPolicy !== null && $robotsPolicy['allowed'] === false) {
            $blockers[] = 'Disallowed by robots.txt rules';
        }

        // 3. Meta robots noindex
        $doc = $snapshot->getParsedDocument();
        if ($doc !== null) {
            $meta = $doc->getRobotsDirectives();
            $hasNoindex = (isset($meta['noindex']) && $meta['noindex'] === true)
                || (isset($meta['none']) && $meta['none'] === true);
            if ($hasNoindex) {
                $blockers[] = 'Meta robots noindex directive present';
            }
        }

        // 4. X-Robots-Tag noindex
        foreach ($response->getHeaderValues('X-Robots-Tag') as $tag) {
            $parts = array_map('trim', explode(',', strtolower($tag)));
            if (in_array('noindex', $parts, true) || in_array('none', $parts, true)) {
                $blockers[] = 'HTTP X-Robots-Tag noindex directive present';
                break;
            }
        }

        // 5. Redirect loop
        if ($snapshot->isRedirectLoopDetected() || $snapshot->isMaxHopsExceeded()) {
            $blockers[] = 'Redirect loop or maximum redirect limit exceeded';
        }

        $isTechnicallyIndexable = empty($blockers);

        if ($isTechnicallyIndexable) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [[
                    'indexable' => true,
                    'status_code' => $statusCode,
                    'disclaimer' => 'Technically indexable; not a guarantee of Google indexing or search rank.',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::WARN,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            evidence: [[
                'indexable' => false,
                'blockers' => $blockers,
                'disclaimer' => 'Page has technical indexability blockers preventing search engines from indexing it.',
            ]],
            affectedUrls: [$snapshot->getFinalUrl()],
            fixKey: 'rules.TECH_INDEXABILITY_INFERENCE.fix'
        );
    }
}

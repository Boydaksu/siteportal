<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class RobotsNoindexConflictRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_ROBOTS_NOINDEX_CONFLICT';
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
        $isDisallowed = $policy !== null && $policy['allowed'] === false;

        $doc = $snapshot->getParsedDocument();
        $hasMetaNoindex = false;
        if ($doc !== null) {
            $meta = $doc->getRobotsDirectives();
            $hasMetaNoindex = (isset($meta['noindex']) && $meta['noindex'] === true)
                || (isset($meta['none']) && $meta['none'] === true);
        }

        $response = $snapshot->getHttpResponse();
        $hasXRobotsNoindex = false;
        if ($response !== null) {
            foreach ($response->getHeaderValues('X-Robots-Tag') as $h) {
                $parts = array_map('trim', explode(',', strtolower($h)));
                if (in_array('noindex', $parts, true) || in_array('none', $parts, true)) {
                    $hasXRobotsNoindex = true;
                    break;
                }
            }
        }

        $hasNoindex = $hasMetaNoindex || $hasXRobotsNoindex;

        if ($isDisallowed && $hasNoindex) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [[
                    'robots_disallowed' => true,
                    'has_noindex' => true,
                    'conflict_explanation' => 'Robots.txt disallow prevents crawling to read noindex directive',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_ROBOTS_NOINDEX_CONFLICT.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'crawler',
            evidence: [[
                'conflict_detected' => false,
                'robots_disallowed' => $isDisallowed,
                'has_noindex' => $hasNoindex,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class CanonicalRedirectRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_CANONICAL_REDIRECT';
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
        $doc = $snapshot->getParsedDocument();
        $canonical = $doc?->getCanonical();

        if ($canonical === null || trim($canonical) === '') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [['note' => 'No canonical URL declared to check redirect status']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $trimmedCanonical = trim($canonical);
        $redirectHistory = $snapshot->getRedirectHistory();
        $isCanonicalRedirecting = false;
        $matchedHop = null;

        // Check if canonical URL was one of the intermediate redirected URLs
        foreach ($redirectHistory as $hop) {
            if (rtrim($hop['url'], '/') === rtrim($trimmedCanonical, '/')) {
                $isCanonicalRedirecting = true;
                $matchedHop = $hop;
                break;
            }
        }

        if ($isCanonicalRedirecting) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                evidence: [[
                    'canonical_url' => $trimmedCanonical,
                    'redirect_detected' => true,
                    'redirected_to' => $snapshot->getFinalUrl(),
                    'matched_hop' => $matchedHop,
                    'warning' => 'Canonical URL points to a redirected URL rather than the final destination',
                ]],
                affectedUrls: [$snapshot->getFinalUrl(), $trimmedCanonical],
                fixKey: 'rules.TECH_CANONICAL_REDIRECT.fix'
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
                'canonical_url' => $trimmedCanonical,
                'redirect_detected' => false,
                'points_to_final' => true,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

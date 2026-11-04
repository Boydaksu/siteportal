<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class CanonicalMultiConflictRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_CANONICAL_MULTI_CONFLICT';
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
        $allCanonicals = [];

        // 1. From HTML document
        $doc = $snapshot->getParsedDocument();
        if ($doc !== null) {
            foreach ($doc->getAllCanonicals() as $c) {
                $trimmed = trim($c);
                if ($trimmed !== '') {
                    $allCanonicals[] = $trimmed;
                }
            }
        }

        // 2. From HTTP Link header (e.g. Link: <https://example.com/canonical>; rel="canonical")
        $response = $snapshot->getHttpResponse();
        if ($response !== null) {
            foreach ($response->getHeaderValues('Link') as $linkHeader) {
                if (preg_match('/<([^>]+)>;\s*rel=["\']?canonical["\']?/i', $linkHeader, $matches)) {
                    $allCanonicals[] = trim($matches[1]);
                }
            }
        }

        $uniqueCanonicals = array_values(array_unique(array_filter($allCanonicals)));

        if (count($uniqueCanonicals) > 1) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'conflict_detected' => true,
                    'count' => count($uniqueCanonicals),
                    'conflicting_canonicals' => $uniqueCanonicals,
                    'error' => 'Multiple conflicting canonical URLs specified across HTML and HTTP headers',
                ]],
                affectedUrls: array_merge([$snapshot->getFinalUrl()], $uniqueCanonicals),
                fixKey: 'rules.TECH_CANONICAL_MULTI_CONFLICT.fix'
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
                'conflict_detected' => false,
                'count' => count($uniqueCanonicals),
                'canonical' => $uniqueCanonicals[0] ?? null,
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

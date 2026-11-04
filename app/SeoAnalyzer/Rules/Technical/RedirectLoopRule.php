<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class RedirectLoopRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_REDIRECT_LOOP';
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
        $chain = $snapshot->getRedirectHistory();
        $urls = array_map(static fn(array $hop): string => $hop['url'], $chain);

        $hasDuplicateInHistory = count($urls) !== count(array_unique($urls));
        $isLoop = $snapshot->isRedirectLoopDetected() || $hasDuplicateInHistory;
        $isMaxHops = $snapshot->isMaxHopsExceeded();

        if ($isLoop || $isMaxHops) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'loop_detected' => $isLoop,
                    'max_hops_exceeded' => $isMaxHops,
                    'chain' => $chain,
                ]],
                affectedUrls: array_values(array_unique(array_merge([$snapshot->getRequestedUrl()], $urls))),
                fixKey: 'rules.TECH_REDIRECT_LOOP.fix'
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
                'loop_detected' => false,
                'max_hops_exceeded' => false,
            ]],
            affectedUrls: [$snapshot->getRequestedUrl()]
        );
    }
}

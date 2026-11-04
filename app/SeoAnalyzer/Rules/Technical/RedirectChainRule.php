<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class RedirectChainRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_REDIRECT_CHAIN';
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
        $chain = $snapshot->getRedirectHistory();
        $hops = count($chain);
        $reqUrl = $snapshot->getRequestedUrl();
        $finalUrl = $snapshot->getFinalUrl();

        if ($hops === 0) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'hop_count' => 0,
                    'chain' => [],
                    'note' => 'No redirects encountered',
                ]],
                affectedUrls: [$reqUrl]
            );
        }

        if ($hops === 1) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'hop_count' => 1,
                    'chain' => $chain,
                    'note' => 'Single redirect step is optimal',
                ]],
                affectedUrls: [$reqUrl, $finalUrl]
            );
        }

        if ($hops === 2) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'http',
                evidence: [[
                    'hop_count' => 2,
                    'chain' => $chain,
                    'warning' => 'Two redirect hops introduce extra latency',
                ]],
                affectedUrls: [$reqUrl, $finalUrl],
                fixKey: 'rules.TECH_REDIRECT_CHAIN.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::FAIL,
            severity: FindingSeverity::HIGH,
            confidence: Confidence::HIGH,
            dataSource: 'http',
            evidence: [[
                'hop_count' => $hops,
                'chain' => $chain,
                'error' => 'Excessive redirect chain wastes crawl budget and delays page load',
            ]],
            affectedUrls: [$reqUrl, $finalUrl],
            fixKey: 'rules.TECH_REDIRECT_CHAIN.fix'
        );
    }
}

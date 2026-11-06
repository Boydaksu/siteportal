<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\Core\Rendering\BlockedResource;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * TECH_JS_BLOCKED_RESOURCES Rule.
 *
 * Checks if critical JavaScript scripts or CSS stylesheets were blocked
 * (by robots.txt, network errors, or timeout) during rendering.
 * Blocking critical assets prevents Googlebot and other crawlers from correctly rendering the DOM.
 */
final class TechJsBlockedResourcesRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_JS_BLOCKED_RESOURCES';
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
        $blockedResources = $snapshot->getBlockedResources();

        // Render network verisi yoksa veya opt-in kapalıysa: sahte ceza verilmez
        if ($blockedResources === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'browser_worker',
                evidence: [
                    [
                        'measured' => false,
                        'reason' => 'Selective JavaScript rendering was not enabled or network tracking unavailable.',
                        'disclaimer' => 'Static HTML analysis mode: Blocked resource verification requires '
                            . 'browser rendering network tracking; no synthetic penalty is applied.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $criticalBlocked = array_values(array_filter(
            $blockedResources,
            static fn (BlockedResource $resource): bool => $resource->isCritical()
        ));

        // Kritik JS veya CSS kaynağı engellenmiş
        if (count($criticalBlocked) > 0) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'browser_worker',
                evidence: [
                    [
                        'critical_blocked_count' => count($criticalBlocked),
                        'total_blocked_count' => count($blockedResources),
                        'blocked_resources' => array_map(
                            static fn (BlockedResource $r): array => $r->toArray(),
                            $criticalBlocked
                        ),
                        'reason' => sprintf(
                            '%d kritik JS veya CSS kaynağının yüklenmesi engellendi (robots.txt veya ağ hatası).',
                            count($criticalBlocked)
                        ),
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_JS_BLOCKED_RESOURCES.fix'
            );
        }

        // Tüm kritik kaynaklar sorunsuz yüklendi
        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'browser_worker',
            evidence: [
                [
                    'critical_blocked_count' => 0,
                    'total_blocked_count' => count($blockedResources),
                    'all_critical_resources_loaded' => true,
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

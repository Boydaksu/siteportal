<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Mobile;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * MOBILE_OVERFLOW Rule.
 *
 * Checks for horizontal layout overflow on mobile screens.
 * In purely static HTML analysis without rendered viewport or PageSpeed lab data,
 * this check safely yields NOT_APPLICABLE to prevent false positives and ungrounded penalties.
 */
final class MobileOverflowRule implements RuleInterface
{
    public const string RULE_ID = 'MOBILE_OVERFLOW';
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
        return 'mobile';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::MEDIUM;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $mobileAudits = $snapshot->getMobileAudits();

        // Harici/Lighthouse mobile audit verisi varsa değerlendir
        if ($mobileAudits !== null && isset($mobileAudits['content_width'])) {
            $passed = (bool) ($mobileAudits['content_width']['passed'] ?? true);
            if (!$passed) {
                return new Finding(
                    ruleId: self::RULE_ID,
                    ruleVersion: self::RULE_VERSION,
                    status: FindingStatus::FAIL,
                    severity: FindingSeverity::MEDIUM,
                    confidence: Confidence::HIGH,
                    dataSource: 'pagespeed_lab',
                    evidence: [
                        [
                            'content_width_audit' => $mobileAudits['content_width'],
                            'reason' => 'Content width exceeds mobile viewport width',
                        ],
                    ],
                    affectedUrls: [$snapshot->getFinalUrl()],
                    fixKey: 'rules.MOBILE_OVERFLOW.fix'
                );
            }

            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'pagespeed_lab',
                evidence: [
                    [
                        'content_width_audit' => $mobileAudits['content_width'],
                        'fits_viewport' => true,
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        // Statik analizde render motoru veya lab verisi yoksa: sahte puan/ceza üretilmez
        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::NOT_APPLICABLE,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [
                [
                    'measured' => false,
                    'reason' => 'Static HTML analysis cannot verify layout overflow without headless rendering '
                        . 'or PageSpeed lab data.',
                    'disclaimer' => 'Horizontal overflow audit requires browser rendering metrics; '
                        . 'no synthetic penalty is applied in static analysis.',
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

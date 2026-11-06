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
 * MOBILE_VIEWPORT Rule.
 *
 * Checks if a meta viewport tag exists and is properly configured for mobile devices.
 * - Missing viewport -> FAIL (MEDIUM)
 * - Restrictive zoom (user-scalable=no, maximum-scale < 2.0) -> WARN (LOW, WCAG 1.4.4)
 * - Missing width=device-width or initial-scale -> WARN (MEDIUM)
 * - Properly configured responsive viewport -> PASS
 */
final class MobileViewportRule implements RuleInterface
{
    public const string RULE_ID = 'MOBILE_VIEWPORT';
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
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $viewport = $doc->getViewport();

        // 1. Viewport hiç yok veya boş
        if ($viewport === null || trim($viewport) === '') {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'viewport' => null,
                        'reason' => 'Meta viewport tag is missing or empty',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.MOBILE_VIEWPORT.fix_missing'
            );
        }

        $vpLower = strtolower($viewport);
        $hasDeviceWidth = str_contains($vpLower, 'width=device-width');
        $hasInitialScale = (bool) preg_match('/initial-scale\s*=\s*[0-9.]+/i', $vpLower);

        // 2. Kısıtlayıcı yakınlaştırma (WCAG 1.4.4 Resize text ihlali)
        $hasUserScalableNo = (bool) preg_match('/user-scalable\s*=\s*(no|0)/i', $vpLower);
        $hasRestrictiveMaxScale = false;
        if (preg_match('/maximum-scale\s*=\s*([0-9.]+)/i', $vpLower, $m)) {
            $maxScale = (float) $m[1];
            if ($maxScale < 2.0) {
                $hasRestrictiveMaxScale = true;
            }
        }

        if ($hasUserScalableNo || $hasRestrictiveMaxScale) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'viewport' => $viewport,
                        'user_scalable_disabled' => $hasUserScalableNo,
                        'restrictive_max_scale' => $hasRestrictiveMaxScale,
                        'wcag_reference' => 'WCAG 2.1 1.4.4 Resize text',
                        'reason' => 'Viewport restricts user zooming or scaling',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.MOBILE_VIEWPORT.fix_scalable'
            );
        }

        // 3. width=device-width veya initial-scale içermiyorsa
        if (!$hasDeviceWidth && !$hasInitialScale) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'viewport' => $viewport,
                        'has_device_width' => false,
                        'has_initial_scale' => false,
                        'reason' => 'Viewport does not specify width=device-width or initial-scale',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.MOBILE_VIEWPORT.fix_invalid'
            );
        }

        // 4. Geçerli responsive viewport
        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [
                [
                    'viewport' => $viewport,
                    'has_device_width' => $hasDeviceWidth,
                    'has_initial_scale' => $hasInitialScale,
                    'is_responsive' => true,
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

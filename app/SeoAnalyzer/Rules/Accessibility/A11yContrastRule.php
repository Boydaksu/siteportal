<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Accessibility;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * A11Y_CONTRAST Rule.
 *
 * Evaluates automated text and background color contrast compliance.
 * Requires computed style / Lighthouse accessibility audit data.
 * In purely static HTML analysis without rendered styles, it safely yields NOT_APPLICABLE
 * to prevent false positives and ungrounded penalties.
 *
 * Notice: This automated check does NOT claim or certify full WCAG conformance.
 */
final class A11yContrastRule implements RuleInterface
{
    public const string RULE_ID = 'A11Y_CONTRAST';
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
        return 'accessibility';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::LOW;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $a11yAudits = $snapshot->getAccessibilityAudits();

        // Harici/Lighthouse accessibility audit verisi varsa değerlendir
        if ($a11yAudits !== null && isset($a11yAudits['color_contrast'])) {
            $audit = $a11yAudits['color_contrast'];
            $passed = (bool) ($audit['passed'] ?? true);

            if (!$passed) {
                return new Finding(
                    ruleId: self::RULE_ID,
                    ruleVersion: self::RULE_VERSION,
                    status: FindingStatus::FAIL,
                    severity: FindingSeverity::LOW,
                    confidence: Confidence::HIGH,
                    dataSource: 'pagespeed_lab',
                    evidence: [
                        [
                            'color_contrast_audit' => $audit,
                            'reason' => 'Background and foreground colors do not have a sufficient contrast ratio',
                            'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir; '
                                . 'otomatik ölçülen kontrast eksiklikleri kanıtla gösterilmiştir.',
                        ],
                    ],
                    affectedUrls: [$snapshot->getFinalUrl()],
                    fixKey: 'rules.A11Y_CONTRAST.fix'
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
                        'color_contrast_audit' => $audit,
                        'sufficient_contrast' => true,
                        'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        // Statik analizde computed style veya lab verisi yoksa: sahte değerlendirme yapılmaz
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
                    'reason' => 'Static HTML analysis cannot reliably evaluate color contrast '
                        . 'without computed CSS styles or Lighthouse audit data.',
                    'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir; '
                        . 'kontrast denetimi için render/lab ölçümü gereklidir, '
                        . 'statik analizde ceza uygulanmaz.',
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

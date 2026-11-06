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
 * A11Y_FORM_LABEL Rule.
 *
 * Checks if applicable form inputs (<input>, <select>, <textarea>) have accessible labels.
 * Excludes hidden, submit, button, reset, image inputs.
 * Labels can be: label[for], wrapped label, aria-label, aria-labelledby, or title.
 *
 * Notice: This automated audit reports observable non-compliance and does NOT certify
 * full WCAG conformance.
 */
final class A11yFormLabelRule implements RuleInterface
{
    public const string RULE_ID = 'A11Y_FORM_LABEL';
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

        $allInputs = $doc->getFormInputs();
        $applicableInputs = array_values(array_filter($allInputs, static fn (array $i): bool => !$i['is_excluded']));

        // 1. Sayfada etiket gerektiren hiçbir form öğesi yoksa
        if (count($applicableInputs) === 0) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'applicable_inputs_count' => 0,
                        'total_inputs_count' => count($allInputs),
                        'reason' => 'No applicable form input controls (<input>, <select>, <textarea>) found on page',
                        'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $missingLabels = $doc->getFormInputsMissingLabels();

        // 2. Eksik etiketli form öğeleri varsa
        if (count($missingLabels) > 0) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'applicable_inputs_count' => count($applicableInputs),
                        'missing_labels_count' => count($missingLabels),
                        'missing_inputs' => $missingLabels,
                        'reason' => sprintf('%d form öğesinde erişilebilir etiket bulunamadı.', count($missingLabels)),
                        'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir; '
                            . 'uyumsuz sinyaller kanıtla gösterilmiştir.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.A11Y_FORM_LABEL.fix'
            );
        }

        // 3. Tüm form öğeleri etiketli
        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [
                [
                    'applicable_inputs_count' => count($applicableInputs),
                    'missing_labels_count' => 0,
                    'disclaimer' => 'Erişilebilirlik tam WCAG sertifikası değildir.',
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

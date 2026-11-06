<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Schema;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * SCHEMA_NO_RICH_TYPE Rule.
 *
 * Checks if the page contains at least one Google Rich Result eligible Schema type.
 * Note: Having no rich result type is NOT an error and does not penalize score (INFO severity).
 * Not every page requires rich results (e.g. Terms of Service, simple contact pages).
 */
final class SchemaNoRichTypeRule implements RuleInterface
{
    public const string RULE_ID = 'SCHEMA_NO_RICH_TYPE';
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
        return 'schema';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::INFO;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::INFO,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $structuredData = $doc->getStructuredData();
        $hasRich = $structuredData !== null && $structuredData->hasRichResultType();

        if ($hasRich) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::PASS,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'status' => 'Page includes Google Rich Result eligible structured data type(s)',
                        'detected_types' => $structuredData->getDetectedTypes(),
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $detectedTypes = $structuredData?->getDetectedTypes() ?? [];

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [
                [
                    'note' => 'No Google Rich Result eligible type detected. '
                        . 'This is informational; not all pages need rich snippets.',
                    'detected_types' => $detectedTypes,
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

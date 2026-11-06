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
 * SCHEMA_REQUIRED_FOR_TYPE Rule.
 *
 * Checks if Google Rich Result supported types (e.g. Product, Article, Recipe, BreadcrumbList)
 * are missing mandatory fields required by Google's Structured Data guidelines.
 */
final class SchemaRequiredForTypeRule implements RuleInterface
{
    public const string RULE_ID = 'SCHEMA_REQUIRED_FOR_TYPE';
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

        $structuredData = $doc->getStructuredData();
        if ($structuredData === null || !$structuredData->hasAnyStructuredData()) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No structured data items found on the page']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        if (!$structuredData->hasRichResultType()) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [
                    [
                        'note' => 'No Google Rich Result eligible types detected; required field checks not applicable',
                        'detected_types' => $structuredData->getDetectedTypes(),
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $itemsWithMissingFields = [];

        foreach ($structuredData->getItems() as $item) {
            $missing = $item->getMissingRequiredProperties();
            if (!empty($missing)) {
                $itemsWithMissingFields[] = [
                    'format' => $item->getFormat(),
                    'type' => $item->getType(),
                    'missing_required' => $missing,
                    'missing_recommended' => $item->getMissingRecommendedProperties(),
                ];
            }
        }

        if (!empty($itemsWithMissingFields)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: $itemsWithMissingFields,
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [
                [
                    'status' => 'All Rich Result types have their required properties present',
                    'detected_types' => $structuredData->getDetectedTypes(),
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

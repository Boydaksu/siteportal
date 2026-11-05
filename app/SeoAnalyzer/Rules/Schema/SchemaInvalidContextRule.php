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
 * SCHEMA_INVALID_CONTEXT Rule.
 *
 * Checks if structured data entities have a valid Schema.org context and non-empty type.
 * Entities pointing to invalid contexts or with missing/unknown types are flagged.
 */
final class SchemaInvalidContextRule implements RuleInterface
{
    public const string RULE_ID = 'SCHEMA_INVALID_CONTEXT';
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

        $items = $structuredData->getItems();
        $invalidEntities = [];

        foreach ($items as $item) {
            $isInvalid = false;
            $reason = '';

            if ($item->getType() === '' || $item->getType() === 'unknown') {
                $isInvalid = true;
                $reason = 'Type (tür) eksik veya geçersiz';
            } elseif (!$item->isSchemaOrg() && $item->getContext() !== null) {
                $isInvalid = true;
                $reason = sprintf('Context (%s) Schema.org standartlarına uymuyor', (string) $item->getContext());
            }

            if ($isInvalid) {
                $invalidEntities[] = [
                    'format' => $item->getFormat(),
                    'type' => $item->getType(),
                    'context' => $item->getContext(),
                    'reason' => $reason,
                ];
            }
        }

        if (!empty($invalidEntities)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: $invalidEntities,
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
                    'item_count' => count($items),
                    'types' => $structuredData->getDetectedTypes(),
                    'status' => 'All structured data entities have valid Schema.org context and type',
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

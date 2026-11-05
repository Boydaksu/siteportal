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
 * SCHEMA_INVALID_JSONLD Rule.
 *
 * Checks if JSON-LD scripts on the page have syntax errors or fail json_decode.
 * Failure indicates invalid structured data markup that search engines cannot parse.
 */
final class SchemaInvalidJsonLdRule implements RuleInterface
{
    public const string RULE_ID = 'SCHEMA_INVALID_JSONLD';
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
        if ($structuredData === null || !$structuredData->hasJsonLd()) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No JSON-LD structured data scripts found on the page']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $syntaxErrors = $structuredData->getSyntaxErrors();
        if (!empty($syntaxErrors)) {
            $evidence = array_map(
                static fn(array $err): array => [
                    'script_index' => $err['script_index'],
                    'error' => $err['error'],
                    'snippet' => $err['snippet'],
                ],
                $syntaxErrors
            );

            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::FAIL,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: $evidence,
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
                    'script_count' => count($structuredData->getRawJsonLdScripts()),
                    'status' => 'All JSON-LD blocks are valid JSON',
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

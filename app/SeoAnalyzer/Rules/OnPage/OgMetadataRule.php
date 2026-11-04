<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

final class OgMetadataRule implements RuleInterface
{
    public const string RULE_ID = 'ONPAGE_OG_METADATA';
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
        return 'onpage';
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

        $og = $doc->getOpenGraph();
        $hasTitle = !empty($og['title']) || !empty($og['og:title']);
        $hasImage = !empty($og['image']) || !empty($og['og:image']);

        $missingProps = [];
        if (!$hasTitle) {
            $missingProps[] = 'og:title';
        }
        if (!$hasImage) {
            $missingProps[] = 'og:image';
        }

        if (!empty($missingProps)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'missing_properties' => $missingProps,
                    'declared_properties' => array_keys($og),
                    'note' => 'Basic Open Graph properties (og:title, og:image) are recommended '
                        . 'for rich previews on social networks',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.ONPAGE_OG_METADATA.fix'
            );
        }

        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'parser',
            evidence: [[
                'declared_properties' => array_keys($og),
                'og_title' => $og['title'] ?? ($og['og:title'] ?? null),
                'og_image' => $og['image'] ?? ($og['og:image'] ?? null),
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

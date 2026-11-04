<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\OnPage;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * @phpstan-import-type ImageItem from \SitePortal\Core\Parser\ParsedDocument
 */
final class ImageAltRule implements RuleInterface
{
    public const string RULE_ID = 'A11Y_IMAGE_ALT';
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
        $doc = $snapshot->getParsedDocument();
        if ($doc === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::UNKNOWN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::UNKNOWN,
                dataSource: 'parser',
                evidence: [['reason' => 'Parsed document not available']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        $images = $doc->getImages();
        if (empty($images)) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [['note' => 'No images found in document; image alt check not applicable']],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        // WCAG standard: alt="" is valid decorative image and NOT missing.
        // Only images where alt === null (attribute omitted entirely) are missing.
        $missingAlt = $doc->getImagesMissingAlt();

        if (!empty($missingAlt)) {
            $samples = array_map(
                static fn(array $img): array => [
                    'src' => $img['src'],
                    'title' => $img['title'],
                ],
                array_slice($missingAlt, 0, 10)
            );

            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::LOW,
                confidence: Confidence::HIGH,
                dataSource: 'parser',
                evidence: [[
                    'missing_count' => count($missingAlt),
                    'total_images' => count($images),
                    'samples' => $samples,
                    'warning' => 'Images without an alt attribute degrade accessibility and miss image search context',
                ]],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.A11Y_IMAGE_ALT.fix'
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
                'missing_count' => 0,
                'total_images' => count($images),
                'message' => 'All images have valid alt attributes or intentional empty alt="" for decorative images',
            ]],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

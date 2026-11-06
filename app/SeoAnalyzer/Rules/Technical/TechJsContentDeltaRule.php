<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules\Technical;

use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;

/**
 * TECH_JS_CONTENT_DELTA Rule.
 *
 * Compares raw static HTML source with browser-rendered DOM.
 * If client-side JavaScript injects major content, links, or headings (heavy hydration),
 * warns about potential crawl budget, indexing delays, or accessibility gaps.
 * When selective rendering is disabled or unavailable, safely yields NOT_APPLICABLE.
 */
final class TechJsContentDeltaRule implements RuleInterface
{
    public const string RULE_ID = 'TECH_JS_CONTENT_DELTA';
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
        return 'technical';
    }

    public function getDefaultSeverity(): FindingSeverity
    {
        return FindingSeverity::MEDIUM;
    }

    public function evaluate(PageSnapshot $snapshot): Finding
    {
        $domDiff = $snapshot->getDomDiff();

        // JS rendering verisi yoksa veya opt-in kapalıysa: sahte ceza verilmez
        if ($domDiff === null) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::NOT_APPLICABLE,
                severity: FindingSeverity::INFO,
                confidence: Confidence::HIGH,
                dataSource: 'browser_worker',
                evidence: [
                    [
                        'measured' => false,
                        'reason' => 'Selective JavaScript rendering was not enabled or worker data unavailable.',
                        'disclaimer' => 'Static HTML analysis mode: JS content delta requires opt-in '
                            . 'browser rendering; no synthetic penalty is applied.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()]
            );
        }

        // İstemci tarafı yoğun hydration farkı tespit edildi
        if ($domDiff->isHydrationHeavy()) {
            return new Finding(
                ruleId: self::RULE_ID,
                ruleVersion: self::RULE_VERSION,
                status: FindingStatus::WARN,
                severity: FindingSeverity::MEDIUM,
                confidence: Confidence::HIGH,
                dataSource: 'browser_worker',
                evidence: [
                    [
                        'raw_word_count' => $domDiff->getRawWordCount(),
                        'rendered_word_count' => $domDiff->getRenderedWordCount(),
                        'word_delta' => $domDiff->getWordCountDelta(),
                        'word_ratio' => $domDiff->getWordCountRatio(),
                        'new_links_count' => count($domDiff->getNewLinks()),
                        'new_headings_count' => count($domDiff->getNewHeadings()),
                        'title_changed' => $domDiff->isTitleChanged(),
                        'reason' => 'A significant portion of core page content or links relies entirely '
                            . 'on client-side JavaScript execution.',
                    ],
                ],
                affectedUrls: [$snapshot->getFinalUrl()],
                fixKey: 'rules.TECH_JS_CONTENT_DELTA.fix'
            );
        }

        // Kaynak HTML ve Rendered DOM tutarlı
        return new Finding(
            ruleId: self::RULE_ID,
            ruleVersion: self::RULE_VERSION,
            status: FindingStatus::PASS,
            severity: FindingSeverity::INFO,
            confidence: Confidence::HIGH,
            dataSource: 'browser_worker',
            evidence: [
                [
                    'raw_word_count' => $domDiff->getRawWordCount(),
                    'rendered_word_count' => $domDiff->getRenderedWordCount(),
                    'word_ratio' => $domDiff->getWordCountRatio(),
                    'content_consistent' => true,
                ],
            ],
            affectedUrls: [$snapshot->getFinalUrl()]
        );
    }
}

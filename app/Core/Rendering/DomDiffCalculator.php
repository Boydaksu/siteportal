<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

use SitePortal\Core\Parser\ParsedDocument;

/**
 * Calculates structural, textual, and navigational differences
 * between raw static HTML and client-side rendered DOM.
 */
final class DomDiffCalculator
{
    /**
     * Minimum word count increase ratio (e.g. 1.4 = +40%) to consider hydration heavy.
     */
    public const float HYDRATION_HEAVY_RATIO_THRESHOLD = 1.4;

    /**
     * Absolute word count threshold for hydration heavy evaluation.
     */
    public const int HYDRATION_HEAVY_MIN_WORDS = 50;

    /**
     * Compares raw ParsedDocument with rendered ParsedDocument.
     */
    public function calculate(ParsedDocument $rawDoc, ParsedDocument $renderedDoc): DomDiffResult
    {
        $rawWords = $rawDoc->getWordCount();
        $renderedWords = $renderedDoc->getWordCount();
        $rawLength = $rawDoc->getTextLength();
        $renderedLength = $renderedDoc->getTextLength();

        // 1. Bağlantı (links) farkı
        $rawHrefs = array_unique(array_filter(array_map(
            static fn (array $l): string => trim($l['href']),
            $rawDoc->getLinks()
        )));
        $renderedHrefs = array_unique(array_filter(array_map(
            static fn (array $l): string => trim($l['href']),
            $renderedDoc->getLinks()
        )));

        $newLinks = array_values(array_diff($renderedHrefs, $rawHrefs));
        $removedLinks = array_values(array_diff($rawHrefs, $renderedHrefs));

        // 2. Başlık (headings) farkı
        $rawHeadingTexts = array_map(
            static fn (array $h): string => $h['tag'] . ': ' . trim($h['text']),
            $rawDoc->getHeadings()
        );
        $renderedHeadingTexts = array_map(
            static fn (array $h): string => $h['tag'] . ': ' . trim($h['text']),
            $renderedDoc->getHeadings()
        );

        $newHeadings = array_values(array_diff($renderedHeadingTexts, $rawHeadingTexts));

        // 3. Title ve Description farkı
        $titleChanged = ($rawDoc->getTitle() !== $renderedDoc->getTitle());
        $metaDescriptionChanged = ($rawDoc->getMetaDescription() !== $renderedDoc->getMetaDescription());

        // 4. Client-side Hydration ağırlığı tespiti
        $isHydrationHeavy = false;
        if ($rawWords < self::HYDRATION_HEAVY_MIN_WORDS && $renderedWords >= self::HYDRATION_HEAVY_MIN_WORDS) {
            $isHydrationHeavy = true;
        } elseif ($rawWords > 0 && ($renderedWords / $rawWords) >= self::HYDRATION_HEAVY_RATIO_THRESHOLD) {
            $isHydrationHeavy = true;
        }

        return new DomDiffResult(
            rawWordCount: $rawWords,
            renderedWordCount: $renderedWords,
            rawTextLength: $rawLength,
            renderedTextLength: $renderedLength,
            rawLinksCount: count($rawDoc->getLinks()),
            renderedLinksCount: count($renderedDoc->getLinks()),
            newLinks: $newLinks,
            removedLinks: $removedLinks,
            newHeadings: $newHeadings,
            titleChanged: $titleChanged,
            metaDescriptionChanged: $metaDescriptionChanged,
            isHydrationHeavy: $isHydrationHeavy
        );
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Rendering;

/**
 * Immutable value object representing the structural and content delta
 * between raw source HTML and browser-rendered DOM.
 */
final class DomDiffResult
{
    /**
     * @param list<string> $newLinks
     * @param list<string> $removedLinks
     * @param list<string> $newHeadings
     */
    public function __construct(
        private readonly int $rawWordCount,
        private readonly int $renderedWordCount,
        private readonly int $rawTextLength,
        private readonly int $renderedTextLength,
        private readonly int $rawLinksCount,
        private readonly int $renderedLinksCount,
        private readonly array $newLinks = [],
        private readonly array $removedLinks = [],
        private readonly array $newHeadings = [],
        private readonly bool $titleChanged = false,
        private readonly bool $metaDescriptionChanged = false,
        private readonly bool $isHydrationHeavy = false
    ) {
    }

    public function getRawWordCount(): int
    {
        return $this->rawWordCount;
    }

    public function getRenderedWordCount(): int
    {
        return $this->renderedWordCount;
    }

    public function getWordCountDelta(): int
    {
        return $this->renderedWordCount - $this->rawWordCount;
    }

    public function getWordCountRatio(): float
    {
        if ($this->rawWordCount === 0) {
            return $this->renderedWordCount > 0 ? 2.0 : 1.0;
        }

        return round($this->renderedWordCount / $this->rawWordCount, 3);
    }

    public function getRawTextLength(): int
    {
        return $this->rawTextLength;
    }

    public function getRenderedTextLength(): int
    {
        return $this->renderedTextLength;
    }

    public function getRawLinksCount(): int
    {
        return $this->rawLinksCount;
    }

    public function getRenderedLinksCount(): int
    {
        return $this->renderedLinksCount;
    }

    /**
     * @return list<string>
     */
    public function getNewLinks(): array
    {
        return $this->newLinks;
    }

    /**
     * @return list<string>
     */
    public function getRemovedLinks(): array
    {
        return $this->removedLinks;
    }

    /**
     * @return list<string>
     */
    public function getNewHeadings(): array
    {
        return $this->newHeadings;
    }

    public function isTitleChanged(): bool
    {
        return $this->titleChanged;
    }

    public function isMetaDescriptionChanged(): bool
    {
        return $this->metaDescriptionChanged;
    }

    public function isHydrationHeavy(): bool
    {
        return $this->isHydrationHeavy;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw_word_count' => $this->rawWordCount,
            'rendered_word_count' => $this->renderedWordCount,
            'word_count_delta' => $this->getWordCountDelta(),
            'word_count_ratio' => $this->getWordCountRatio(),
            'raw_text_length' => $this->rawTextLength,
            'rendered_text_length' => $this->renderedTextLength,
            'raw_links_count' => $this->rawLinksCount,
            'rendered_links_count' => $this->renderedLinksCount,
            'new_links_count' => count($this->newLinks),
            'new_links' => $this->newLinks,
            'removed_links_count' => count($this->removedLinks),
            'new_headings_count' => count($this->newHeadings),
            'new_headings' => $this->newHeadings,
            'title_changed' => $this->titleChanged,
            'meta_description_changed' => $this->metaDescriptionChanged,
            'is_hydration_heavy' => $this->isHydrationHeavy,
        ];
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser\StructuredData;

/**
 * Encapsulates the complete structured data extraction result for a web page.
 */
final class StructuredDataResult
{
    /**
     * @param list<ExtractedItem> $items
     * @param list<array{script_index: int, error: string, snippet: string}> $syntaxErrors
     * @param list<string> $rawJsonLdScripts
     */
    public function __construct(
        private readonly array $items = [],
        private readonly array $syntaxErrors = [],
        private readonly array $rawJsonLdScripts = []
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return list<ExtractedItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return list<array{script_index: int, error: string, snippet: string}>
     */
    public function getSyntaxErrors(): array
    {
        return $this->syntaxErrors;
    }

    public function hasSyntaxErrors(): bool
    {
        return !empty($this->syntaxErrors);
    }

    /**
     * @return list<string>
     */
    public function getRawJsonLdScripts(): array
    {
        return $this->rawJsonLdScripts;
    }

    public function hasAnyStructuredData(): bool
    {
        return !empty($this->items) || !empty($this->syntaxErrors) || !empty($this->rawJsonLdScripts);
    }

    public function hasJsonLd(): bool
    {
        if (!empty($this->rawJsonLdScripts) || !empty($this->syntaxErrors)) {
            return true;
        }

        foreach ($this->items as $item) {
            if ($item->getFormat() === 'json-ld') {
                return true;
            }
        }

        return false;
    }

    public function hasMicrodata(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getFormat() === 'microdata') {
                return true;
            }
        }

        return false;
    }

    public function hasRdfa(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getFormat() === 'rdfa') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns unique schema types detected across all items.
     *
     * @return list<string>
     */
    public function getDetectedTypes(): array
    {
        $types = [];
        foreach ($this->items as $item) {
            $t = $item->getType();
            if ($t !== '' && !in_array($t, $types, true)) {
                $types[] = $t;
            }
        }

        return $types;
    }

    /**
     * Returns whether at least one item has a Google Rich Result eligible type.
     */
    public function hasRichResultType(): bool
    {
        $richTypes = [
            'Article', 'NewsArticle', 'BlogPosting',
            'Product', 'Offer', 'AggregateOffer',
            'Recipe', 'Review', 'AggregateRating',
            'BreadcrumbList', 'FAQPage', 'QAPage',
            'Event', 'LocalBusiness', 'Restaurant',
            'JobPosting', 'VideoObject', 'Course',
            'Book', 'HowTo', 'SoftwareApplication',
        ];

        foreach ($this->getDetectedTypes() as $type) {
            if (in_array($type, $richTypes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'has_structured_data' => $this->hasAnyStructuredData(),
            'has_json_ld' => $this->hasJsonLd(),
            'has_microdata' => $this->hasMicrodata(),
            'has_rdfa' => $this->hasRdfa(),
            'detected_types' => $this->getDetectedTypes(),
            'has_rich_result_type' => $this->hasRichResultType(),
            'has_syntax_errors' => $this->hasSyntaxErrors(),
            'syntax_errors_count' => count($this->syntaxErrors),
            'items_count' => count($this->items),
            'items' => array_map(static fn (ExtractedItem $item): array => $item->toArray(), $this->items),
            'syntax_errors' => $this->syntaxErrors,
        ];
    }
}

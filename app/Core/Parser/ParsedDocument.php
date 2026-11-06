<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser;

use SitePortal\Core\Parser\StructuredData\StructuredDataResult;

/**
 * ParsedDocument — HTML Ayrıştırma Sonucu Veri Nesnesi (Value Object)
 *
 * DOMDocument ve DOMXPath tarafından çıkarılan tüm meta verileri, başlıkları,
 * kanonik bağlantıları, Open Graph / Twitter kartlarını, görselleri ve linkleri
 * tip güvenli ve değişmez (immutable) şekilde saklar.
 *
 * @phpstan-type HeadingItem array{tag: string, level: int, text: string, index: int}
 * @phpstan-type ImageItem array{
 *     src: string,
 *     alt: ?string,
 *     title: ?string,
 *     width: ?string,
 *     height: ?string,
 *     loading: ?string,
 *     role?: ?string,
 *     aria_hidden?: bool
 * }
 * @phpstan-type FormInputItem array{
 *     tag: string,
 *     id: ?string,
 *     name: ?string,
 *     type: string,
 *     has_label: bool,
 *     label_text: ?string,
 *     label_source: ?string,
 *     is_excluded: bool
 * }
 * @phpstan-type LinkItem array{
 *     href: string,
 *     resolved_url: string,
 *     text: string,
 *     title: ?string,
 *     rel: ?string,
 *     target: ?string,
 *     is_external: bool,
 *     is_nofollow: bool
 * }
 */
final class ParsedDocument
{
    /**
     * @param list<string> $allTitles
     * @param list<string> $allMetaDescriptions
     * @param list<string> $allCanonicals
     * @param list<HeadingItem> $headings
     * @param array<string, bool|string|int> $robotsDirectives
     * @param array<string, string> $openGraph
     * @param array<string, string> $twitterCards
     * @param list<ImageItem> $images
     * @param list<LinkItem> $links
     * @param list<FormInputItem> $formInputs
     */
    public function __construct(
        private readonly ?string $title,
        private readonly int $titleCount,
        private readonly array $allTitles,
        private readonly ?string $metaDescription,
        private readonly int $metaDescriptionCount,
        private readonly array $allMetaDescriptions,
        private readonly ?string $lang,
        private readonly ?string $canonical,
        private readonly int $canonicalCount,
        private readonly array $allCanonicals,
        private readonly array $headings,
        private readonly array $robotsDirectives,
        private readonly array $openGraph,
        private readonly array $twitterCards,
        private readonly array $images,
        private readonly array $links,
        private readonly ?string $charset,
        private readonly int $rawByteLength,
        private readonly int $textLength,
        private readonly int $wordCount,
        private readonly float $parseTimeMs,
        private readonly bool $isMalformed,
        private readonly ?StructuredDataResult $structuredData = null,
        private readonly ?string $viewport = null,
        private readonly array $formInputs = []
    ) {
    }

    public function getViewport(): ?string
    {
        return $this->viewport;
    }

    /**
     * @return list<FormInputItem>
     */
    public function getFormInputs(): array
    {
        return $this->formInputs;
    }

    /**
     * @return list<FormInputItem>
     */
    public function getFormInputsMissingLabels(): array
    {
        $missing = [];
        foreach ($this->formInputs as $input) {
            if (!$input['is_excluded'] && !$input['has_label']) {
                $missing[] = $input;
            }
        }

        return $missing;
    }

    public function getStructuredData(): ?StructuredDataResult
    {
        return $this->structuredData;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getTitleCount(): int
    {
        return $this->titleCount;
    }

    /**
     * @return list<string>
     */
    public function getAllTitles(): array
    {
        return $this->allTitles;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getMetaDescriptionCount(): int
    {
        return $this->metaDescriptionCount;
    }

    /**
     * @return list<string>
     */
    public function getAllMetaDescriptions(): array
    {
        return $this->allMetaDescriptions;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    public function getCanonicalCount(): int
    {
        return $this->canonicalCount;
    }

    /**
     * @return list<string>
     */
    public function getAllCanonicals(): array
    {
        return $this->allCanonicals;
    }

    /**
     * @return list<HeadingItem>
     */
    public function getHeadings(): array
    {
        return $this->headings;
    }

    /**
     * @return list<HeadingItem>
     */
    public function getHeadingsByLevel(int $level): array
    {
        $result = [];
        foreach ($this->headings as $h) {
            if ($h['level'] === $level) {
                $result[] = $h;
            }
        }

        return $result;
    }

    public function getH1Count(): int
    {
        return count($this->getHeadingsByLevel(1));
    }

    /**
     * @return list<string>
     */
    public function getH1Texts(): array
    {
        $texts = [];
        foreach ($this->getHeadingsByLevel(1) as $h1) {
            $texts[] = $h1['text'];
        }

        return $texts;
    }

    /**
     * @return array<string, bool|string|int>
     */
    public function getRobotsDirectives(): array
    {
        return $this->robotsDirectives;
    }

    public function hasRobotsDirective(string $directive): bool
    {
        $normalized = strtolower(trim($directive));
        return isset($this->robotsDirectives[$normalized]) && $this->robotsDirectives[$normalized] === true;
    }

    public function isNoindex(): bool
    {
        return $this->hasRobotsDirective('noindex');
    }

    public function isNofollow(): bool
    {
        return $this->hasRobotsDirective('nofollow');
    }

    /**
     * @return array<string, string>
     */
    public function getOpenGraph(): array
    {
        return $this->openGraph;
    }

    public function getOpenGraphProperty(string $property): ?string
    {
        $key = str_starts_with($property, 'og:') ? substr($property, 3) : $property;
        return $this->openGraph[$key] ?? $this->openGraph[$property] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getTwitterCards(): array
    {
        return $this->twitterCards;
    }

    public function getTwitterCardProperty(string $property): ?string
    {
        $key = str_starts_with($property, 'twitter:') ? substr($property, 8) : $property;
        return $this->twitterCards[$key] ?? $this->twitterCards[$property] ?? null;
    }

    /**
     * @return list<ImageItem>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function getImageCount(): int
    {
        return count($this->images);
    }

    /**
     * Alt niteliği hiç olmayan (missing) görselleri döndürür.
     * Boş alt="" geçerli dekoratif görseldir (WCAG standardı), bu yüzden alt !== null olanlar eksik sayılmaz.
     *
     * @return list<ImageItem>
     */
    public function getImagesMissingAlt(): array
    {
        $missing = [];
        foreach ($this->images as $img) {
            $isExplicitlyDecorative = ($img['role'] ?? null) === 'presentation'
                || ($img['role'] ?? null) === 'none'
                || ($img['aria_hidden'] ?? false) === true;

            if ($img['alt'] === null && !$isExplicitlyDecorative) {
                $missing[] = $img;
            }
        }

        return $missing;
    }

    /**
     * @return list<LinkItem>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function getLinkCount(): int
    {
        return count($this->links);
    }

    /**
     * @return list<LinkItem>
     */
    public function getInternalLinks(): array
    {
        $internal = [];
        foreach ($this->links as $link) {
            if (!$link['is_external']) {
                $internal[] = $link;
            }
        }

        return $internal;
    }

    /**
     * @return list<LinkItem>
     */
    public function getExternalLinks(): array
    {
        $external = [];
        foreach ($this->links as $link) {
            if ($link['is_external']) {
                $external[] = $link;
            }
        }

        return $external;
    }

    public function getCharset(): ?string
    {
        return $this->charset;
    }

    public function getRawByteLength(): int
    {
        return $this->rawByteLength;
    }

    public function getTextLength(): int
    {
        return $this->textLength;
    }

    public function getWordCount(): int
    {
        return $this->wordCount;
    }

    public function getParseTimeMs(): float
    {
        return $this->parseTimeMs;
    }

    public function isMalformed(): bool
    {
        return $this->isMalformed;
    }

    /**
     * JSON serileştirme veya kural girdi formatı.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'title_count' => $this->titleCount,
            'all_titles' => $this->allTitles,
            'meta_description' => $this->metaDescription,
            'meta_description_count' => $this->metaDescriptionCount,
            'all_meta_descriptions' => $this->allMetaDescriptions,
            'lang' => $this->lang,
            'canonical' => $this->canonical,
            'canonical_count' => $this->canonicalCount,
            'all_canonicals' => $this->allCanonicals,
            'headings' => $this->headings,
            'h1_count' => $this->getH1Count(),
            'robots_directives' => $this->robotsDirectives,
            'is_noindex' => $this->isNoindex(),
            'is_nofollow' => $this->isNofollow(),
            'open_graph' => $this->openGraph,
            'twitter_cards' => $this->twitterCards,
            'images' => $this->images,
            'images_missing_alt_count' => count($this->getImagesMissingAlt()),
            'links' => $this->links,
            'internal_link_count' => count($this->getInternalLinks()),
            'external_link_count' => count($this->getExternalLinks()),
            'charset' => $this->charset,
            'raw_byte_length' => $this->rawByteLength,
            'text_length' => $this->textLength,
            'word_count' => $this->wordCount,
            'parse_time_ms' => $this->parseTimeMs,
            'is_malformed' => $this->isMalformed,
            'structured_data' => $this->structuredData?->toArray(),
            'viewport' => $this->viewport,
            'form_inputs_count' => count($this->formInputs),
            'form_inputs_missing_labels_count' => count($this->getFormInputsMissingLabels()),
        ];
    }
}

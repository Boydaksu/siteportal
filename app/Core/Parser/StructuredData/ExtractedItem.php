<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser\StructuredData;

/**
 * Represents a single extracted structured data item (JSON-LD, Microdata, or RDFa).
 */
final class ExtractedItem
{
    /**
     * @param string $format 'json-ld' | 'microdata' | 'rdfa'
     * @param string $type e.g. 'Article', 'Product', 'Organization', 'WebSite'
     * @param array<string|int, mixed> $data Extracted key-value attributes
     * @param string|null $context Context URL (e.g. 'https://schema.org')
     * @param bool $isSchemaOrg Whether context is schema.org
     * @param bool $hasSyntaxError Whether item encountered syntax/parsing failure
     * @param string|null $syntaxErrorMessage Syntax error details if any
     * @param list<string> $missingRequiredProperties Google Rich Result required properties missing
     * @param list<string> $missingRecommendedProperties Google Rich Result recommended properties missing
     */
    public function __construct(
        private readonly string $format,
        private readonly string $type,
        private readonly array $data,
        private readonly ?string $context = null,
        private readonly bool $isSchemaOrg = true,
        private readonly bool $hasSyntaxError = false,
        private readonly ?string $syntaxErrorMessage = null,
        private readonly array $missingRequiredProperties = [],
        private readonly array $missingRecommendedProperties = []
    ) {
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function isSchemaOrg(): bool
    {
        return $this->isSchemaOrg;
    }

    public function hasSyntaxError(): bool
    {
        return $this->hasSyntaxError;
    }

    public function getSyntaxErrorMessage(): ?string
    {
        return $this->syntaxErrorMessage;
    }

    /**
     * @return list<string>
     */
    public function getMissingRequiredProperties(): array
    {
        return $this->missingRequiredProperties;
    }

    /**
     * @return list<string>
     */
    public function getMissingRecommendedProperties(): array
    {
        return $this->missingRecommendedProperties;
    }

    public function isRichResultEligible(): bool
    {
        return !$this->hasSyntaxError
            && $this->isSchemaOrg
            && empty($this->missingRequiredProperties)
            && $this->type !== ''
            && $this->type !== 'Thing';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'type' => $this->type,
            'context' => $this->context,
            'is_schema_org' => $this->isSchemaOrg,
            'has_syntax_error' => $this->hasSyntaxError,
            'syntax_error_message' => $this->syntaxErrorMessage,
            'missing_required' => $this->missingRequiredProperties,
            'missing_recommended' => $this->missingRecommendedProperties,
            'is_rich_result_eligible' => $this->isRichResultEligible(),
            'data' => $this->data,
        ];
    }
}

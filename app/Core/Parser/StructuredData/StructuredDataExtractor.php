<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser\StructuredData;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts and parses structured data (JSON-LD, Microdata, and RDFa) from DOM.
 * Strictly validates Schema.org semantics and distinguishes syntax errors from eligibility recommendations.
 */
final class StructuredDataExtractor
{
    /**
     * @return StructuredDataResult
     */
    public static function extract(DOMDocument $dom): StructuredDataResult
    {
        $xpath = new DOMXPath($dom);

        $syntaxErrors = [];
        $rawScripts = [];
        $items = [];

        // 1. JSON-LD Extraction
        self::extractJsonLd($xpath, $items, $syntaxErrors, $rawScripts);

        // 2. Microdata Extraction
        self::extractMicrodata($xpath, $items);

        // 3. RDFa Extraction
        self::extractRdfa($xpath, $items);

        return new StructuredDataResult(
            items: $items,
            syntaxErrors: $syntaxErrors,
            rawJsonLdScripts: $rawScripts
        );
    }

    /**
     * @param list<ExtractedItem> $items
     * @param list<array{script_index: int, error: string, snippet: string}> $syntaxErrors
     * @param list<string> $rawScripts
     */
    private static function extractJsonLd(
        DOMXPath $xpath,
        array &$items,
        array &$syntaxErrors,
        array &$rawScripts
    ): void {
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        if ($scripts === false) {
            return;
        }

        $scriptIndex = 0;
        foreach ($scripts as $script) {
            if (!$script instanceof DOMElement) {
                continue;
            }
            $scriptIndex++;
            $textContent = trim($script->textContent);
            if ($textContent === '') {
                continue;
            }

            $rawScripts[] = $textContent;

            /** @var mixed $decoded */
            $decoded = json_decode($textContent, true, 512, JSON_BIGINT_AS_STRING);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $syntaxErrors[] = [
                    'script_index' => $scriptIndex,
                    'error' => json_last_error_msg(),
                    'snippet' => mb_substr($textContent, 0, 300),
                ];
                continue;
            }

            self::processJsonLdNode($decoded, $items);
        }
    }

    /**
     * Recursively processes decoded JSON-LD node(s) handling @graph arrays and multiple entities.
     *
     * @param array<string|int, mixed> $node
     * @param list<ExtractedItem> $items
     * @param string|null $parentContext
     */
    private static function processJsonLdNode(array $node, array &$items, ?string $parentContext = null): void
    {
        // Handle numeric array of items
        if (array_is_list($node)) {
            foreach ($node as $subNode) {
                if (is_array($subNode)) {
                    self::processJsonLdNode($subNode, $items, $parentContext);
                }
            }
            return;
        }

        // Handle @graph container
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            $context = isset($node['@context']) && is_string($node['@context'])
                ? $node['@context']
                : $parentContext;

            foreach ($node['@graph'] as $graphItem) {
                if (is_array($graphItem)) {
                    self::processJsonLdNode($graphItem, $items, $context);
                }
            }
            return;
        }

        /** @var array<string, mixed> $node */

        /** @var string|null $context */
        $context = isset($node['@context']) && is_string($node['@context'])
            ? $node['@context']
            : $parentContext;

        $type = self::normalizeType($node['@type'] ?? null);
        $isSchema = self::isSchemaOrgContext($context);

        [$missingReq, $missingRec] = self::evaluateRequirements($type, $node);

        $hasSyntaxError = false;
        $syntaxMsg = null;

        if ($type === '' || $type === 'unknown') {
            $syntaxMsg = 'Eksik veya geçersiz @type tanımı';
        } elseif (!$isSchema && $context !== null) {
            $syntaxMsg = 'Schema.org harici veya geçersiz @context (' . (string) $context . ')';
        }

        $items[] = new ExtractedItem(
            format: 'json-ld',
            type: $type,
            data: $node,
            context: $context,
            isSchemaOrg: $isSchema,
            hasSyntaxError: $hasSyntaxError,
            syntaxErrorMessage: $syntaxMsg,
            missingRequiredProperties: $missingReq,
            missingRecommendedProperties: $missingRec
        );
    }

    /**
     * @param list<ExtractedItem> $items
     */
    private static function extractMicrodata(DOMXPath $xpath, array &$items): void
    {
        $itemscopes = $xpath->query('//*[@itemscope and not(ancestor::*[@itemscope])]');
        if ($itemscopes === false) {
            return;
        }

        foreach ($itemscopes as $scope) {
            if (!$scope instanceof DOMElement) {
                continue;
            }

            $itemtype = trim($scope->getAttribute('itemtype'));
            $type = self::extractTypeFromUrl($itemtype);
            $isSchema = str_contains($itemtype, 'schema.org');

            $properties = self::extractMicrodataProperties($scope);
            [$missingReq, $missingRec] = self::evaluateRequirements($type, $properties);

            $hasSyntaxError = $type === '' || $type === 'unknown';
            $syntaxMsg = $hasSyntaxError ? 'itemscope tanımlı ancak itemtype belirtilmemiş' : null;

            $items[] = new ExtractedItem(
                format: 'microdata',
                type: $type,
                data: $properties,
                context: $itemtype !== '' ? $itemtype : null,
                isSchemaOrg: $isSchema,
                hasSyntaxError: $hasSyntaxError,
                syntaxErrorMessage: $syntaxMsg,
                missingRequiredProperties: $missingReq,
                missingRecommendedProperties: $missingRec
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractMicrodataProperties(DOMElement $element): array
    {
        $props = [];
        $xpath = new DOMXPath($element->ownerDocument ?? new DOMDocument());
        $propNodes = $xpath->query('.//*[@itemprop]', $element);

        if ($propNodes !== false) {
            foreach ($propNodes as $propNode) {
                if (!$propNode instanceof DOMElement) {
                    continue;
                }

                $name = trim($propNode->getAttribute('itemprop'));
                if ($name === '') {
                    continue;
                }

                $val = self::getElementContent($propNode);
                if (isset($props[$name])) {
                    if (!is_array($props[$name])) {
                        $props[$name] = [$props[$name]];
                    }
                    $props[$name][] = $val;
                } else {
                    $props[$name] = $val;
                }
            }
        }

        return $props;
    }

    /**
     * @param list<ExtractedItem> $items
     */
    private static function extractRdfa(DOMXPath $xpath, array &$items): void
    {
        $rdfaNodes = $xpath->query('//*[@typeof and not(ancestor::*[@typeof])]');
        if ($rdfaNodes === false) {
            return;
        }

        foreach ($rdfaNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $typeof = trim($node->getAttribute('typeof'));
            $vocab = trim($node->getAttribute('vocab'));
            if ($vocab === '') {
                // Check ancestor vocab
                $ancestor = $node->parentNode;
                while ($ancestor instanceof DOMElement) {
                    if ($ancestor->hasAttribute('vocab')) {
                        $vocab = trim($ancestor->getAttribute('vocab'));
                        break;
                    }
                    $ancestor = $ancestor->parentNode;
                }
            }

            $type = self::extractTypeFromUrl($typeof);
            $isSchema = str_contains($vocab, 'schema.org') || str_contains($typeof, 'schema.org');

            $properties = self::extractRdfaProperties($node);
            [$missingReq, $missingRec] = self::evaluateRequirements($type, $properties);

            $hasSyntaxError = $type === '' || $type === 'unknown';
            $syntaxMsg = $hasSyntaxError ? 'typeof belirtilmiş ancak geçerli bir tür bulunamadı' : null;

            $items[] = new ExtractedItem(
                format: 'rdfa',
                type: $type,
                data: $properties,
                context: $vocab !== '' ? $vocab : null,
                isSchemaOrg: $isSchema,
                hasSyntaxError: $hasSyntaxError,
                syntaxErrorMessage: $syntaxMsg,
                missingRequiredProperties: $missingReq,
                missingRecommendedProperties: $missingRec
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractRdfaProperties(DOMElement $element): array
    {
        $props = [];
        $xpath = new DOMXPath($element->ownerDocument ?? new DOMDocument());
        $propNodes = $xpath->query('.//*[@property]', $element);

        if ($propNodes !== false) {
            foreach ($propNodes as $propNode) {
                if (!$propNode instanceof DOMElement) {
                    continue;
                }

                $name = trim($propNode->getAttribute('property'));
                if ($name === '') {
                    continue;
                }

                $val = self::getElementContent($propNode);
                $props[$name] = $val;
            }
        }

        return $props;
    }

    private static function getElementContent(DOMElement $element): string
    {
        if ($element->hasAttribute('content')) {
            return trim($element->getAttribute('content'));
        }
        if ($element->hasAttribute('href')) {
            return trim($element->getAttribute('href'));
        }
        if ($element->hasAttribute('src')) {
            return trim($element->getAttribute('src'));
        }
        return trim($element->textContent);
    }

    private static function normalizeType(mixed $rawType): string
    {
        if (is_string($rawType)) {
            return self::extractTypeFromUrl($rawType);
        }

        if (is_array($rawType)) {
            foreach ($rawType as $t) {
                if (is_string($t)) {
                    $extracted = self::extractTypeFromUrl($t);
                    if ($extracted !== '' && $extracted !== 'Thing') {
                        return $extracted;
                    }
                }
            }
            return 'Thing';
        }

        return 'unknown';
    }

    private static function extractTypeFromUrl(string $urlOrType): string
    {
        $trimmed = trim($urlOrType);
        if ($trimmed === '') {
            return 'unknown';
        }

        $parts = explode('/', rtrim($trimmed, '/'));
        $last = end($parts);

        if (str_contains($last, '#')) {
            $hashParts = explode('#', $last);
            $last = end($hashParts);
        }

        return $last !== '' ? $last : 'unknown';
    }

    private static function isSchemaOrgContext(?string $context): bool
    {
        if ($context === null) {
            return false;
        }

        return str_contains($context, 'schema.org');
    }

    /**
     * Evaluates Google Rich Result required and recommended fields.
     *
     * @param array<string, mixed> $data
     * @return array{0: list<string>, 1: list<string>} [missingRequired, missingRecommended]
     */
    private static function evaluateRequirements(string $type, array $data): array
    {
        $required = [];
        $recommended = [];

        switch ($type) {
            case 'Product':
                if (empty($data['name'])) {
                    $required[] = 'name';
                }
                if (empty($data['image'])) {
                    $recommended[] = 'image';
                }
                if (empty($data['offers']) && empty($data['review']) && empty($data['aggregateRating'])) {
                    $recommended[] = 'offers/review/aggregateRating';
                }
                break;

            case 'Article':
            case 'NewsArticle':
            case 'BlogPosting':
                if (empty($data['headline']) && empty($data['name'])) {
                    $required[] = 'headline';
                }
                if (empty($data['image'])) {
                    $recommended[] = 'image';
                }
                if (empty($data['datePublished'])) {
                    $recommended[] = 'datePublished';
                }
                if (empty($data['author'])) {
                    $recommended[] = 'author';
                }
                break;

            case 'BreadcrumbList':
                if (empty($data['itemListElement'])) {
                    $required[] = 'itemListElement';
                }
                break;

            case 'FAQPage':
                if (empty($data['mainEntity'])) {
                    $required[] = 'mainEntity';
                }
                break;

            case 'LocalBusiness':
            case 'Restaurant':
                if (empty($data['name'])) {
                    $required[] = 'name';
                }
                if (empty($data['address'])) {
                    $required[] = 'address';
                }
                break;

            case 'Recipe':
                if (empty($data['name'])) {
                    $required[] = 'name';
                }
                if (empty($data['recipeIngredient']) && empty($data['ingredients'])) {
                    $required[] = 'recipeIngredient';
                }
                break;

            case 'Event':
                if (empty($data['name'])) {
                    $required[] = 'name';
                }
                if (empty($data['startDate'])) {
                    $required[] = 'startDate';
                }
                if (empty($data['location'])) {
                    $required[] = 'location';
                }
                break;

            case 'JobPosting':
                if (empty($data['title'])) {
                    $required[] = 'title';
                }
                if (empty($data['description'])) {
                    $required[] = 'description';
                }
                if (empty($data['datePosted'])) {
                    $required[] = 'datePosted';
                }
                break;
        }

        return [$required, $recommended];
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Core\Crawler\Sitemap;

use SitePortal\Core\Crawler\Sitemap\Exceptions\MalformedSitemapException;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapLimitExceededException;
use SitePortal\Core\Crawler\Sitemap\Exceptions\SitemapSecurityException;
use XMLReader;

/**
 * Secure, Streaming XML Sitemap Parser.
 *
 * Implements XMLReader streaming parser with strict XXE, XML Bomb, entity expansion,
 * and 50k URL / 50MB limit guards per Google and sitemaps.org specification.
 */
final class SitemapXmlParser
{
    /**
     * Standard sitemaps.org limits.
     */
    public const MAX_BYTES = 52428800; // 50 MB
    public const MAX_URLS = 50000;      // 50,000 URLs

    private int $maxBytes;
    private int $maxUrls;

    public function __construct(
        int $maxBytes = self::MAX_BYTES,
        int $maxUrls = self::MAX_URLS
    ) {
        $this->maxBytes = $maxBytes;
        $this->maxUrls = $maxUrls;
    }

    /**
     * Parses raw sitemap XML content safely.
     *
     * @param string $content Raw XML or gzip-compressed XML content
     * @return SitemapParseResult Parsed URLs or child sitemap references
     * @throws SitemapSecurityException If XXE, DTD, or XML Bomb is detected
     * @throws SitemapLimitExceededException If size or URL count exceeds limits
     * @throws MalformedSitemapException If XML is invalid or root element is unrecognized
     */
    public function parse(string $content): SitemapParseResult
    {
        $rawLength = strlen($content);
        if ($rawLength === 0) {
            throw new MalformedSitemapException('Sitemap içeriği boş olamaz.');
        }

        // 1. Decompress if gzip format (magic bytes: \x1f\x8b)
        if (str_starts_with($content, "\x1f\x8b")) {
            $decompressed = @gzdecode($content, $this->maxBytes + 1);
            if ($decompressed === false) {
                throw new MalformedSitemapException('Gzip ile sıkıştırılmış sitemap açılamadı.');
            }
            $content = $decompressed;
            $rawLength = strlen($content);
        }

        // 2. Size limit check
        if ($rawLength > $this->maxBytes) {
            throw new SitemapLimitExceededException(
                "Sitemap boyutu izin verilen {$this->maxBytes} bayt (50MB) sınırını aşıyor: {$rawLength} bayt."
            );
        }

        // 3. Proactive XXE / DTD / XML Bomb check
        $this->assertXmlSecurity($content);

        // 4. Streaming XMLReader initialization
        $reader = new XMLReader();

        // Safe parser flags: LIBXML_NONET (prevent network access), LIBXML_COMPACT
        $flags = LIBXML_NONET | LIBXML_COMPACT;

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $opened = $reader->xml($content, 'UTF-8', $flags);
            if (!$opened) {
                throw new MalformedSitemapException('XMLReader sitemap içeriğini yükleyemedi.');
            }

            // Disable DTD loading explicitly
            $reader->setParserProperty(XMLReader::LOADDTD, false);

            return $this->streamRead($reader, $rawLength);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    /**
     * Validates that XML does not contain DTD declarations or entity injections.
     */
    private function assertXmlSecurity(string $xml): void
    {
        // Scan first 8KB or entire string for <!DOCTYPE or <!ENTITY
        $header = substr($xml, 0, 8192);

        if (preg_match('/<!DOCTYPE\b/i', $header) === 1) {
            throw new SitemapSecurityException(
                'Güvenlik ihlali: Sitemap dosyalarında DOCTYPE veya DTD bildirimi kabul edilmez.'
            );
        }

        if (preg_match('/<!ENTITY\b/i', $xml) === 1) {
            throw new SitemapSecurityException(
                'Güvenlik ihlali: Sitemap dosyalarında harici varlık (ENTITY/XXE) kabul edilmez.'
            );
        }
    }

    /**
     * Streams through the XML nodes and extracts <url> or <sitemap> entries.
     */
    private function streamRead(XMLReader $reader, int $byteLength): SitemapParseResult
    {
        $type = SitemapType::UNKNOWN;
        $urls = [];
        $sitemaps = [];

        // Move to the first element (root tag)
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $rootName = strtolower($reader->localName);
                if ($rootName === 'urlset') {
                    $type = SitemapType::URLSET;
                    break;
                }
                if ($rootName === 'sitemapindex') {
                    $type = SitemapType::SITEMAP_INDEX;
                    break;
                }

                throw new MalformedSitemapException(
                    "Bilinmeyen sitemap kök elemanı: <{$reader->localName}>. 'urlset' veya 'sitemapindex' bekleniyordu."
                );
            }
        }

        if ($type === SitemapType::UNKNOWN) {
            throw new MalformedSitemapException('Geçerli bir sitemap kök etiketi bulunamadı.');
        }

        if ($type === SitemapType::URLSET) {
            $urls = $this->readUrlset($reader);
        } else {
            $sitemaps = $this->readSitemapIndex($reader);
        }

        return new SitemapParseResult(
            type: $type,
            urls: $urls,
            sitemaps: $sitemaps,
            byteLength: $byteLength
        );
    }

    /**
     * Reads all <url> entries from <urlset>.
     *
     * @return list<SitemapUrlEntry>
     */
    private function readUrlset(XMLReader $reader): array
    {
        $urls = [];
        $count = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && strtolower($reader->localName) === 'url') {
                $count++;
                if ($count > $this->maxUrls) {
                    throw new SitemapLimitExceededException(
                        "Sitemap URL sayısı izin verilen {$this->maxUrls} sınırını aşıyor."
                    );
                }

                $entry = $this->parseUrlElement($reader);
                if ($entry !== null) {
                    $urls[] = $entry;
                }
            }
        }

        return $urls;
    }

    /**
     * Reads all <sitemap> entries from <sitemapindex>.
     *
     * @return list<SitemapIndexEntry>
     */
    private function readSitemapIndex(XMLReader $reader): array
    {
        $sitemaps = [];
        $count = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && strtolower($reader->localName) === 'sitemap') {
                $count++;
                if ($count > $this->maxUrls) {
                    throw new SitemapLimitExceededException(
                        "Sitemap Index alt-sitemap sayısı izin verilen {$this->maxUrls} sınırını aşıyor."
                    );
                }

                $entry = $this->parseSitemapElement($reader);
                if ($entry !== null) {
                    $sitemaps[] = $entry;
                }
            }
        }

        return $sitemaps;
    }

    /**
     * Parses a single <url> subtree into a SitemapUrlEntry.
     */
    private function parseUrlElement(XMLReader $reader): ?SitemapUrlEntry
    {
        if ($reader->isEmptyElement) {
            return null;
        }

        $loc = null;
        $lastmod = null;
        $changefreq = null;
        $priority = null;

        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && strtolower($reader->localName) === 'url') {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth > $depth) {
                $field = strtolower($reader->localName);
                $value = $reader->readString();

                match ($field) {
                    'loc' => $loc = trim($value),
                    'lastmod' => $lastmod = trim($value),
                    'changefreq' => $changefreq = trim($value),
                    'priority' => $priority = is_numeric($value) ? (float) $value : null,
                    default => null,
                };
            }
        }

        if ($loc === null || $loc === '') {
            return null;
        }

        return new SitemapUrlEntry(
            loc: $loc,
            lastmod: $lastmod,
            changefreq: $changefreq,
            priority: $priority
        );
    }

    /**
     * Parses a single <sitemap> subtree into a SitemapIndexEntry.
     */
    private function parseSitemapElement(XMLReader $reader): ?SitemapIndexEntry
    {
        if ($reader->isEmptyElement) {
            return null;
        }

        $loc = null;
        $lastmod = null;
        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && strtolower($reader->localName) === 'sitemap') {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth > $depth) {
                $field = strtolower($reader->localName);
                $value = $reader->readString();

                match ($field) {
                    'loc' => $loc = trim($value),
                    'lastmod' => $lastmod = trim($value),
                    default => null,
                };
            }
        }

        if ($loc === null || $loc === '') {
            return null;
        }

        return new SitemapIndexEntry(
            loc: $loc,
            lastmod: $lastmod
        );
    }
}

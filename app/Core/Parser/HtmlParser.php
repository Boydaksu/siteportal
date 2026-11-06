<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use SitePortal\Core\Parser\Exceptions\HtmlTooLargeException;
use SitePortal\Core\Parser\StructuredData\StructuredDataExtractor;

/**
 * HtmlParser — Güvenli, UTF-8 uyumlu, tek geçişli HTML ve metadata ayrıştırıcı.
 *
 * DOMDocument ve DOMXPath kullanarak HTML5/HTML4 belgelerinden başlık, meta açıklaması,
 * başlık hiyerarşisi (H1-H6), dil, kanonik bağlantılar, Open Graph, Twitter Card,
 * görsel (eksik alt tespiti) ve bağlantı (iç/dış) verilerini çıkarır.
 *
 * Güvenlik: LIBXML_NONET bayrağı ile XXE ve dış varlık yükleme saldırılarına karşı korumalıdır.
 * Hata Toleransı: Bozuk, eksik etiketli veya biçimsiz HTML belgelerinde çökmeden ayrıştırma yapar.
 *
 * @phpstan-import-type HeadingItem from ParsedDocument
 * @phpstan-import-type ImageItem from ParsedDocument
 * @phpstan-import-type LinkItem from ParsedDocument
 */
final class HtmlParser implements HtmlParserInterface
{
    /**
     * Varsayılan maksimum HTML gövde boyutu: 5 MB (5.242.880 bayt).
     */
    public const DEFAULT_MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @param int $maxBytes Ayrıştırılacak maksimum HTML boyutu
     */
    public function __construct(
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES
    ) {
    }

    /**
     * HTML belgesini ayrıştırır ve tip güvenli ParsedDocument döndürür.
     *
     * @param string $html Ham HTML içeriği
     * @param string|null $baseUrl Göreceli bağlantıların çözümlenmesi için temel URL
     * @param string|null $charset Varsa HTTP yanıt başlığından gelen karakter kümesi
     * @return ParsedDocument
     * @throws HtmlTooLargeException HTML boyutu $maxBytes sınırını aştığında
     */
    public function parse(string $html, ?string $baseUrl = null, ?string $charset = null): ParsedDocument
    {
        $startTime = microtime(true);
        $rawByteLength = strlen($html);

        if ($rawByteLength > $this->maxBytes) {
            throw new HtmlTooLargeException(
                sprintf('HTML belgesi izin verilen sınırı (%d bayt) aşıyor: %d bayt.', $this->maxBytes, $rawByteLength)
            );
        }

        // Null bayt temizliği
        $cleanHtml = str_replace("\0", '', $html);

        // Karakter kümesi belirleme ve UTF-8 normalizasyonu
        [$cleanHtml, $detectedCharset] = $this->normalizeEncoding($cleanHtml, $charset);

        // DOM oluşturma ve libxml korumaları (LIBXML_NONET = XXE koruması)
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prevErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        // XML öneki kaldır ve DOM yüklemesi için karakter kümesini UTF-8'e sabitle
        $strippedHtml = preg_replace('/^<\?xml[^>]*\?>/i', '', ltrim($cleanHtml)) ?? $cleanHtml;
        // libxml HTML ayrıştırıcısının UTF-8'e dönüştürülmüş içeriği eski karakter kümesiyle
        // yeniden yorumlamasını engelle
        $forDom = preg_replace('/(charset=[\'"]?)[a-zA-Z0-9_\-]+/i', '${1}UTF-8', $strippedHtml) ?? $strippedHtml;
        $htmlToLoad = '<?xml encoding="UTF-8">' . $forDom;

        $dom->loadHTML($htmlToLoad, LIBXML_NONET);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        $isMalformed = $this->hasSignificantXmlErrors($xmlErrors);
        $xpath = new DOMXPath($dom);

        // Base URL belirleme (<base href="..."> kontrolü)
        $effectiveBaseUrl = $this->determineEffectiveBaseUrl($xpath, $baseUrl);

        // 1. Title etiketleri
        $allTitles = $this->extractTitles($xpath);
        $titleCount = count($allTitles);
        $title = $titleCount > 0 ? $allTitles[0] : null;

        // 2. Meta etiketleri (tek geçişte: description, robots, og, twitter, charset)
        $metaData = $this->extractMetaTags($xpath);
        $allMetaDescriptions = $metaData['descriptions'];
        $metaDescriptionCount = count($allMetaDescriptions);
        $metaDescription = $metaDescriptionCount > 0 ? $allMetaDescriptions[0] : null;
        $robotsDirectives = $metaData['robots'];
        $openGraph = $metaData['og'];
        $twitterCards = $metaData['twitter'];
        $documentCharset = $detectedCharset ?? $metaData['charset'] ?? 'UTF-8';

        // 3. Dil (<html lang="..."> veya xml:lang)
        $lang = $this->extractLanguage($xpath);

        // 4. Canonical linkleri (<link rel="canonical" href="...">)
        $allCanonicals = $this->extractCanonicals($xpath, $effectiveBaseUrl);
        $canonicalCount = count($allCanonicals);
        $canonical = $canonicalCount > 0 ? $allCanonicals[0] : null;

        // 5. Başlık hiyerarşisi (h1 - h6)
        $headings = $this->extractHeadings($xpath);

        // 6. Görseller (<img>)
        $images = $this->extractImages($xpath);

        // 7. Bağlantılar (<a>)
        $links = $this->extractLinks($xpath, $effectiveBaseUrl);

        // 8. Yapılandırılmış Veri (JSON-LD, Microdata, RDFa - script'ler silinmeden önce çıkarılır)
        $structuredData = StructuredDataExtractor::extract($dom);

        // 9. Gövde metin uzunluğu ve kelime sayısı (script, style vb. temizlendikten sonra)
        [$textLength, $wordCount] = $this->calculateTextAndWords($xpath);

        $parseTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return new ParsedDocument(
            title: $title,
            titleCount: $titleCount,
            allTitles: $allTitles,
            metaDescription: $metaDescription,
            metaDescriptionCount: $metaDescriptionCount,
            allMetaDescriptions: $allMetaDescriptions,
            lang: $lang,
            canonical: $canonical,
            canonicalCount: $canonicalCount,
            allCanonicals: $allCanonicals,
            headings: $headings,
            robotsDirectives: $robotsDirectives,
            openGraph: $openGraph,
            twitterCards: $twitterCards,
            images: $images,
            links: $links,
            charset: $documentCharset,
            rawByteLength: $rawByteLength,
            textLength: $textLength,
            wordCount: $wordCount,
            parseTimeMs: $parseTimeMs,
            isMalformed: $isMalformed,
            structuredData: $structuredData
        );
    }

    /**
     * HTML içeriğini UTF-8 karakter kümesine normalize eder.
     *
     * @return array{0: string, 1: string|null} [Normalize edilmiş HTML, tespit edilen karakter kümesi]
     */
    private function normalizeEncoding(string $html, ?string $charset): array
    {
        $detectedCharset = null;

        if ($charset !== null && trim($charset) !== '') {
            $detectedCharset = strtoupper(trim($charset));
        } else {
            // HTML başından meta charset tespit et (ilk 4KB)
            $sample = substr($html, 0, 4096);
            if (preg_match('/<meta[^>]+charset=["\']?([a-zA-Z0-9_\-]+)/i', $sample, $m)) {
                $detectedCharset = strtoupper($m[1]);
            } elseif (
                preg_match(
                    '/<meta[^>]+http-equiv=["\']?Content-Type["\']?[^>]*content=["\']?'
                    . '[^"\'>]*charset=([a-zA-Z0-9_\-]+)/i',
                    $sample,
                    $m
                )
            ) {
                $detectedCharset = strtoupper($m[1]);
            }
        }

        if ($detectedCharset !== null && $detectedCharset !== 'UTF-8' && $detectedCharset !== 'UTF8') {
            try {
                $converted = mb_convert_encoding($html, 'UTF-8', $detectedCharset);
                if (is_string($converted)) {
                    $html = $converted;
                }
            } catch (\Throwable) {
                // Karakter kümesi dönüştürme başarısız olursa orijinal devam et
            }
        } elseif (!mb_check_encoding($html, 'UTF-8')) {
            $detected = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-9', 'Windows-1254', 'ISO-8859-1'], true);
            if ($detected !== false && $detected !== 'UTF-8') {
                $converted = mb_convert_encoding($html, 'UTF-8', $detected);
                if (is_string($converted)) {
                    $html = $converted;
                    $detectedCharset = $detected;
                }
            }
        }

        return [$html, $detectedCharset];
    }

    /**
     * <base href="..."> etiketi varsa temel URL'i günceller.
     */
    private function determineEffectiveBaseUrl(DOMXPath $xpath, ?string $baseUrl): ?string
    {
        $baseNodes = $xpath->query('//base[@href]');
        if ($baseNodes !== false && $baseNodes->length > 0) {
            $node = $baseNodes->item(0);
            if ($node instanceof DOMElement) {
                $href = trim($node->getAttribute('href'));
                if ($href !== '') {
                    return $this->resolveUrl($href, $baseUrl);
                }
            }
        }

        return $baseUrl;
    }

    /**
     * Tüm <title> etiketlerinin içeriğini sırayla çeker.
     *
     * @return list<string>
     */
    private function extractTitles(DOMXPath $xpath): array
    {
        $titles = [];
        $nodes = $xpath->query('//title');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMNode) {
                    $titles[] = $this->normalizeWhitespace($node->textContent);
                }
            }
        }

        return $titles;
    }

    /**
     * Tek bir XPath taramasıyla tüm meta etiketlerini sınıflandırır.
     *
     * @return array{
     *     descriptions: list<string>,
     *     robots: array<string, bool|string|int>,
     *     og: array<string, string>,
     *     twitter: array<string, string>,
     *     charset: ?string
     * }
     */
    private function extractMetaTags(DOMXPath $xpath): array
    {
        $descriptions = [];
        $robots = [];
        $og = [];
        $twitter = [];
        $charset = null;

        $nodes = $xpath->query('//meta');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                // 1. <meta charset="...">
                if ($node->hasAttribute('charset')) {
                    $c = trim($node->getAttribute('charset'));
                    if ($c !== '') {
                        $charset = strtoupper($c);
                    }
                }

                // 2. <meta http-equiv="Content-Type" content="...">
                $isContentType = $node->hasAttribute('http-equiv')
                    && strcasecmp($node->getAttribute('http-equiv'), 'content-type') === 0;
                if ($isContentType) {
                    $content = $node->getAttribute('content');
                    if (preg_match('/charset=([a-zA-Z0-9_\-]+)/i', $content, $m)) {
                        $charset = strtoupper($m[1]);
                    }
                }

                $content = $node->hasAttribute('content') ? trim($node->getAttribute('content')) : '';

                // 3. name niteliği (description, robots, googlebot, twitter, og)
                if ($node->hasAttribute('name')) {
                    $name = strtolower(trim($node->getAttribute('name')));

                    if ($name === 'description') {
                        $descriptions[] = $this->normalizeWhitespace($content);
                    } elseif ($name === 'robots' || $name === 'googlebot') {
                        $this->parseRobotsDirectives($content, $robots);
                    } elseif (str_starts_with($name, 'twitter:')) {
                        $prop = substr($name, 8);
                        $twitter[$prop] = $content;
                        $twitter[$name] = $content;
                    } elseif (str_starts_with($name, 'og:')) {
                        $prop = substr($name, 3);
                        $og[$prop] = $content;
                        $og[$name] = $content;
                    }
                }

                // 4. property niteliği (og:*, twitter:*)
                if ($node->hasAttribute('property')) {
                    $property = strtolower(trim($node->getAttribute('property')));

                    if (str_starts_with($property, 'og:')) {
                        $prop = substr($property, 3);
                        $og[$prop] = $content;
                        $og[$property] = $content;
                    } elseif (str_starts_with($property, 'twitter:')) {
                        $prop = substr($property, 8);
                        $twitter[$prop] = $content;
                        $twitter[$property] = $content;
                    }
                }
            }
        }

        return [
            'descriptions' => $descriptions,
            'robots' => $robots,
            'og' => $og,
            'twitter' => $twitter,
            'charset' => $charset,
        ];
    }

    /**
     * Robots meta içeriğini ayrıştırıp direktif sözlüğüne ekler.
     *
     * @param array<string, bool|string|int> $robots
     */
    private function parseRobotsDirectives(string $content, array &$robots): void
    {
        $parts = explode(',', $content);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (str_contains($part, ':')) {
                [$key, $val] = explode(':', $part, 2);
                $key = strtolower(trim($key));
                $val = trim($val);
                $robots[$key] = ctype_digit($val) ? (int) $val : $val;
            } else {
                $key = strtolower($part);
                if ($key === 'none') {
                    $robots['noindex'] = true;
                    $robots['nofollow'] = true;
                } elseif ($key === 'all') {
                    $robots['index'] = true;
                    $robots['follow'] = true;
                } else {
                    $robots[$key] = true;
                }
            }
        }
    }

    /**
     * <html lang="..."> veya xml:lang niteliğinden sayfa dilini çıkarır.
     */
    private function extractLanguage(DOMXPath $xpath): ?string
    {
        $htmlNodes = $xpath->query('//html[1]');
        if ($htmlNodes !== false && $htmlNodes->length > 0) {
            $node = $htmlNodes->item(0);
            if ($node instanceof DOMElement) {
                if ($node->hasAttribute('lang')) {
                    $lang = trim($node->getAttribute('lang'));
                    return $lang !== '' ? $lang : null;
                }
                if ($node->hasAttribute('xml:lang')) {
                    $lang = trim($node->getAttribute('xml:lang'));
                    return $lang !== '' ? $lang : null;
                }
            }
        }

        return null;
    }

    /**
     * <link rel="canonical" href="..."> etiketlerini ayıklar ve çözer.
     *
     * @return list<string>
     */
    private function extractCanonicals(DOMXPath $xpath, ?string $baseUrl): array
    {
        $canonicals = [];
        $nodes = $xpath->query('//link[@href]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $rel = strtolower($node->getAttribute('rel'));
                if (preg_match('/\bcanonical\b/i', $rel) === 1) {
                    $href = trim($node->getAttribute('href'));
                    if ($href !== '') {
                        $canonicals[] = $this->resolveUrl($href, $baseUrl);
                    }
                }
            }
        }

        return $canonicals;
    }

    /**
     * h1-h6 başlıklarını belge sırasına göre ayıklar.
     *
     * @return list<HeadingItem>
     */
    private function extractHeadings(DOMXPath $xpath): array
    {
        $headings = [];
        $nodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        if ($nodes !== false) {
            $index = 0;
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($node->nodeName);
                $level = (int) substr($tag, 1);
                $text = $this->normalizeWhitespace($node->textContent);
                $headings[] = [
                    'tag' => $tag,
                    'level' => $level,
                    'text' => $text,
                    'index' => $index++,
                ];
            }
        }

        return $headings;
    }

    /**
     * <img> etiketlerini ayıklar; eksik alt ile boş alt="" ayrımını korur.
     *
     * @return list<ImageItem>
     */
    private function extractImages(DOMXPath $xpath): array
    {
        $images = [];
        $nodes = $xpath->query('//img');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $src = trim($node->getAttribute('src'));
                $alt = $node->hasAttribute('alt') ? $node->getAttribute('alt') : null;
                $title = $node->hasAttribute('title') ? $node->getAttribute('title') : null;
                $width = $node->hasAttribute('width') ? $node->getAttribute('width') : null;
                $height = $node->hasAttribute('height') ? $node->getAttribute('height') : null;
                $loading = $node->hasAttribute('loading') ? strtolower($node->getAttribute('loading')) : null;

                $images[] = [
                    'src' => $src,
                    'alt' => $alt,
                    'title' => $title,
                    'width' => $width,
                    'height' => $height,
                    'loading' => $loading,
                ];
            }
        }

        return $images;
    }

    /**
     * <a href="..."> bağlantılarını ayıklar; iç/dış ve nofollow ayrımını yapar.
     *
     * @return list<LinkItem>
     */
    private function extractLinks(DOMXPath $xpath, ?string $baseUrl): array
    {
        $links = [];
        $nodes = $xpath->query('//a[@href]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $href = trim($node->getAttribute('href'));
                $resolved = $this->resolveUrl($href, $baseUrl);
                $text = $this->normalizeWhitespace($node->textContent);
                $title = $node->hasAttribute('title') ? $node->getAttribute('title') : null;
                $rel = $node->hasAttribute('rel') ? $node->getAttribute('rel') : null;
                $target = $node->hasAttribute('target') ? $node->getAttribute('target') : null;
                $isNofollow = $rel !== null && preg_match('/\bnofollow\b/i', $rel) === 1;
                $isExternal = $this->isExternalLink($resolved, $baseUrl);

                $links[] = [
                    'href' => $href,
                    'resolved_url' => $resolved,
                    'text' => $text,
                    'title' => $title,
                    'rel' => $rel,
                    'target' => $target,
                    'is_external' => $isExternal,
                    'is_nofollow' => $isNofollow,
                ];
            }
        }

        return $links;
    }

    /**
     * Gövde metin uzunluğu ve kelime sayısını hesaplar.
     * Sayfada görünmeyen script, style, noscript, svg, canvas etiketleri elenir.
     *
     * @return array{0: int, 1: int} [textLength, wordCount]
     */
    private function calculateTextAndWords(DOMXPath $xpath): array
    {
        // Görünmeyen etiketleri DOM'dan kaldır
        $unwanted = $xpath->query('//script | //style | //noscript | //template | //svg | //canvas');
        if ($unwanted !== false) {
            foreach ($unwanted as $node) {
                if ($node instanceof DOMNode && $node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $textNodes = $xpath->query('//body//text() | //text()');
        $textParts = [];
        if ($textNodes !== false) {
            foreach ($textNodes as $t) {
                $val = trim($t->nodeValue ?? '');
                if ($val !== '') {
                    $textParts[] = $val;
                }
            }
        }

        $fullText = implode(' ', $textParts);
        $textLength = mb_strlen($fullText, 'UTF-8');

        // Unicode uyumlu kelime sayımı
        preg_match_all('/[\p{L}\p{N}]+/u', $fullText, $words);
        $wordCount = count($words[0]);

        return [$textLength, $wordCount];
    }

    /**
     * RFC 3986 standartlarına uygun göreceli URL çözücü.
     */
    public function resolveUrl(string $relative, ?string $base): string
    {
        $relative = trim($relative);
        if ($base === null || trim($base) === '') {
            return $relative;
        }

        // Web dışı şemalar doğrudan korunur (mailto:, tel:, javascript:, sms:, data:)
        if (preg_match('/^(mailto:|tel:|javascript:|sms:|data:)/i', $relative) === 1) {
            return $relative;
        }

        // Zaten mutlak URL (http:// veya https://)
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $relative) === 1) {
            return $relative;
        }

        $baseParts = parse_url($base);
        if ($baseParts === false || !isset($baseParts['host'])) {
            return $relative;
        }

        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        // Protokole göreli bağlantı (//example.com/asset)
        if (str_starts_with($relative, '//')) {
            return "{$scheme}:{$relative}";
        }

        // Sadece sayfa içi çapa (#section)
        if (str_starts_with($relative, '#')) {
            return $base . $relative;
        }

        $relParts = parse_url($relative);
        $relPath = $relParts['path'] ?? '';
        $query = isset($relParts['query']) ? '?' . $relParts['query'] : '';
        $fragment = isset($relParts['fragment']) ? '#' . $relParts['fragment'] : '';

        // Sadece sorgu dizgesi (?foo=bar)
        if ($relPath === '') {
            $basePath = $baseParts['path'] ?? '/';
            return "{$origin}{$basePath}{$query}{$fragment}";
        }

        // Kök dizine göreli yol (/about/us)
        if (str_starts_with($relPath, '/')) {
            $segments = explode('/', $relPath);
            $normalized = $this->normalizePathSegments($segments);
            $trailingSlash = str_ends_with($relPath, '/') && $normalized !== '/' ? '/' : '';
            return "{$origin}{$normalized}{$trailingSlash}{$query}{$fragment}";
        }

        // Dizin seviyesine göreli yol (subpage, ../other)
        $basePath = $baseParts['path'] ?? '/';
        $lastSlashPos = strrpos($basePath, '/');
        $dir = $lastSlashPos !== false ? substr($basePath, 0, $lastSlashPos) : '';
        $combined = ($dir === '' ? '' : $dir) . '/' . $relPath;

        $segments = explode('/', $combined);
        $normalized = $this->normalizePathSegments($segments);
        $trailingSlash = str_ends_with($relPath, '/') && $normalized !== '/' ? '/' : '';

        return "{$origin}{$normalized}{$trailingSlash}{$query}{$fragment}";
    }

    /**
     * Yol bölütlerindeki . ve .. öğelerini temizler.
     *
     * @param list<string> $segments
     */
    private function normalizePathSegments(array $segments): string
    {
        $resolved = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $seg;
            }
        }

        return '/' . implode('/', $resolved);
    }

    /**
     * Çözümlenen bir URL'in temel URL'e göre dış (harici) bağlantı olup olmadığını belirler.
     */
    private function isExternalLink(string $url, ?string $baseUrl): bool
    {
        if (preg_match('/^(mailto:|tel:|javascript:|sms:|data:|#)/i', $url) === 1) {
            return false;
        }

        if ($baseUrl === null || trim($baseUrl) === '') {
            return preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $url) === 1;
        }

        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $linkHost = parse_url($url, PHP_URL_HOST);

        if ($baseHost !== null && $linkHost !== null) {
            return strcasecmp((string) $linkHost, (string) $baseHost) !== 0;
        }

        return false;
    }

    /**
     * libxml hatalarını inceleyerek gerçek sözdizimi veya yapısal bozukluk olup olmadığını belirler.
     * HTML5 semantik etiketleri (code 801) ve komut dosyası içi etiket kapanışları (code 76) göz ardı edilir.
     *
     * @param list<\LibXMLError> $xmlErrors
     */
    private function hasSignificantXmlErrors(array $xmlErrors): bool
    {
        foreach ($xmlErrors as $e) {
            $msg = trim($e->message);
            // HTML 4.01 ayrıştırıcısının HTML5 etiketlerine verdiği uyarıları yoksay (header, main, section vb.)
            if ($e->code === 801 && preg_match('/^Tag [a-zA-Z0-9_\-]+ invalid$/i', $msg) === 1) {
                continue;
            }
            // Script veya style içeriğindeki beklenmeyen kapanış uyarılarını yoksay
            if ($e->code === 76) {
                continue;
            }
            // Yapısal veya kritik sözdizimi hataları
            $isFatalOrSyntax = $e->level === LIBXML_ERR_FATAL
                || in_array($e->code, [68, 73, 74, 80], true)
                || str_contains($msg, "Couldn't find end of Start Tag");
            if ($isFatalOrSyntax) {
                return true;
            }
        }

        return false;
    }

    /**
     * Metindeki ardışık boşlukları, satır sonlarını ve sekmeleri tek boşluğa indirger.
     */
    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}

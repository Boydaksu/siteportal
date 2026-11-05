<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Export;

use InvalidArgumentException;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Presentation\SiteWidePageItem;

/**
 * MultiPageCsvExporter — Secure CSV exporter for multi-page crawl and site-wide audit results.
 * Guarantees UTF-8 BOM encoding for Excel compatibility and strict OWASP CSV injection mitigation.
 */
final class MultiPageCsvExporter
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Sanitizes a cell value to prevent CSV / Formula injection (OWASP DDE injection).
     * If a string starts with =, +, -, @, tab, or carriage return, prefixes it with a single quote.
     */
    public function sanitizeCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Evet' : 'Hayır';
        }

        $str = (string) $value;

        // Check if string begins with dangerous spreadsheet formula characters
        if (preg_match('/^[\=\+\-\@\t\r]/', $str) === 1) {
            $str = "'" . $str;
        }

        return $str;
    }

    /**
     * Exports a list of crawled pages as a secure CSV string.
     *
     * @param list<SiteWidePageItem> $pages
     */
    public function exportPagesCsv(array $pages): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidArgumentException('Failed to open memory stream for CSV generation.');
        }

        // Write UTF-8 BOM
        fwrite($handle, self::UTF8_BOM);

        // Header row (tr-TR standard)
        $headers = [
            'URL',
            'Durum Kodu',
            'Başlık',
            'Meta Açıklama',
            'Kanonik URL',
            'İndekslenebilir',
            'Tıklama Derinliği',
            'İç Gelen Bağlantı',
            'Giden Bağlantı',
            'Hata Sayısı',
            'Uyarı Sayısı',
            'Sitemap\'te Var',
            'Yetim Sayfa',
        ];
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($pages as $page) {
            $row = [
                $this->sanitizeCellValue($page->getUrl()),
                $this->sanitizeCellValue($page->getStatusCode()),
                $this->sanitizeCellValue($page->getTitle()),
                $this->sanitizeCellValue($page->getMetaDescription()),
                $this->sanitizeCellValue($page->getCanonicalUrl()),
                $this->sanitizeCellValue($page->isIndexable()),
                $this->sanitizeCellValue($page->getDepth()),
                $this->sanitizeCellValue($page->getInlinksCount()),
                $this->sanitizeCellValue($page->getOutlinksCount()),
                $this->sanitizeCellValue($page->getErrorCount()),
                $this->sanitizeCellValue($page->getWarningCount()),
                $this->sanitizeCellValue($page->isInSitemap()),
                $this->sanitizeCellValue($page->isOrphan()),
            ];
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Exports audit findings as a secure CSV string with affected URLs.
     *
     * @param list<Finding> $findings
     */
    public function exportFindingsCsv(array $findings): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidArgumentException('Failed to open memory stream for CSV generation.');
        }

        // Write UTF-8 BOM
        fwrite($handle, self::UTF8_BOM);

        // Header row
        $headers = [
            'Kural Kimliği',
            'Kural Sürümü',
            'Durum',
            'Şiddet',
            'Güven Seviyesi',
            'Veri Kaynağı',
            'Gözlem Zamanı',
            'Etkilenen URL',
            'Kanıt Detayı',
        ];
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($findings as $finding) {
            $affectedUrls = $finding->getAffectedUrls();
            $evidenceJson = json_encode($finding->getEvidence(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (empty($affectedUrls)) {
                $row = [
                    $this->sanitizeCellValue($finding->getRuleId()),
                    $this->sanitizeCellValue($finding->getRuleVersion()),
                    $this->sanitizeCellValue($finding->getStatus()->value),
                    $this->sanitizeCellValue($finding->getSeverity()->value),
                    $this->sanitizeCellValue($finding->getConfidence()->value),
                    $this->sanitizeCellValue($finding->getDataSource()),
                    $this->sanitizeCellValue($finding->getObservedAt()),
                    '',
                    $this->sanitizeCellValue($evidenceJson !== false ? $evidenceJson : ''),
                ];
                fputcsv($handle, $row, ',', '"', '\\');
            } else {
                foreach ($affectedUrls as $url) {
                    $row = [
                        $this->sanitizeCellValue($finding->getRuleId()),
                        $this->sanitizeCellValue($finding->getRuleVersion()),
                        $this->sanitizeCellValue($finding->getStatus()->value),
                        $this->sanitizeCellValue($finding->getSeverity()->value),
                        $this->sanitizeCellValue($finding->getConfidence()->value),
                        $this->sanitizeCellValue($finding->getDataSource()),
                        $this->sanitizeCellValue($finding->getObservedAt()),
                        $this->sanitizeCellValue($url),
                        $this->sanitizeCellValue($evidenceJson !== false ? $evidenceJson : ''),
                    ];
                    fputcsv($handle, $row, ',', '"', '\\');
                }
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Streams pages directly into a writable stream resource for memory efficiency.
     *
     * @param iterable<SiteWidePageItem> $pages
     * @param resource $stream
     */
    public function exportPagesToStream(iterable $pages, $stream): void
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Expected valid resource stream.');
        }

        fwrite($stream, self::UTF8_BOM);

        $headers = [
            'URL',
            'Durum Kodu',
            'Başlık',
            'Meta Açıklama',
            'Kanonik URL',
            'İndekslenebilir',
            'Tıklama Derinliği',
            'İç Gelen Bağlantı',
            'Giden Bağlantı',
            'Hata Sayısı',
            'Uyarı Sayısı',
            'Sitemap\'te Var',
            'Yetim Sayfa',
        ];
        fputcsv($stream, $headers, ',', '"', '\\');

        foreach ($pages as $page) {
            $row = [
                $this->sanitizeCellValue($page->getUrl()),
                $this->sanitizeCellValue($page->getStatusCode()),
                $this->sanitizeCellValue($page->getTitle()),
                $this->sanitizeCellValue($page->getMetaDescription()),
                $this->sanitizeCellValue($page->getCanonicalUrl()),
                $this->sanitizeCellValue($page->isIndexable()),
                $this->sanitizeCellValue($page->getDepth()),
                $this->sanitizeCellValue($page->getInlinksCount()),
                $this->sanitizeCellValue($page->getOutlinksCount()),
                $this->sanitizeCellValue($page->getErrorCount()),
                $this->sanitizeCellValue($page->getWarningCount()),
                $this->sanitizeCellValue($page->isInSitemap()),
                $this->sanitizeCellValue($page->isOrphan()),
            ];
            fputcsv($stream, $row, ',', '"', '\\');
        }
    }
}

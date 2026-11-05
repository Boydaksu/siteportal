<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Export;

use PHPUnit\Framework\TestCase;
use SitePortal\SeoAnalyzer\Domain\Confidence;
use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\FindingStatus;
use SitePortal\SeoAnalyzer\Export\MultiPageCsvExporter;
use SitePortal\SeoAnalyzer\Presentation\SiteWidePageItem;

final class MultiPageCsvExporterTest extends TestCase
{
    private MultiPageCsvExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new MultiPageCsvExporter();
    }

    /**
     * CRITICAL SECURITY CRITERIA:
     * Prevents CSV Formula / DDE Injection attacks.
     */
    public function testSanitizesFormulaInjectionVectors(): void
    {
        $this->assertSame("'=cmd|' /C calc'!A0", $this->exporter->sanitizeCellValue("=cmd|' /C calc'!A0"));
        $this->assertSame("'+1+1", $this->exporter->sanitizeCellValue("+1+1"));
        $this->assertSame("'-2+5", $this->exporter->sanitizeCellValue("-2+5"));
        $this->assertSame("'@SUM(A1:A10)", $this->exporter->sanitizeCellValue("@SUM(A1:A10)"));
        $this->assertSame("'\ttabbed", $this->exporter->sanitizeCellValue("\ttabbed"));
        $this->assertSame("'\rreturn", $this->exporter->sanitizeCellValue("\rreturn"));

        // Normal strings should remain unchanged
        $this->assertSame('Normal Title', $this->exporter->sanitizeCellValue('Normal Title'));
        $this->assertSame('https://example.com/test', $this->exporter->sanitizeCellValue('https://example.com/test'));
        $this->assertSame('Evet', $this->exporter->sanitizeCellValue(true));
        $this->assertSame('Hayır', $this->exporter->sanitizeCellValue(false));
        $this->assertSame('', $this->exporter->sanitizeCellValue(null));
    }

    public function testExportsPagesCsvWithUtf8BomAndHeaders(): void
    {
        $pages = [
            new SiteWidePageItem(
                url: 'https://example.com/',
                statusCode: 200,
                title: 'Türkçe Başlık & Özel Karakterler (ç, ğ, ı, ö, ş, ü)',
                metaDescription: 'Meta açıklaması',
                canonicalUrl: 'https://example.com/',
                isIndexable: true,
                depth: 0,
                inlinksCount: 5,
                outlinksCount: 12,
                isInSitemap: true,
                isOrphan: false
            ),
            new SiteWidePageItem(
                url: 'https://example.com/malicious',
                statusCode: 404,
                title: '=2+5*cmd', // Injection vector
                metaDescription: null,
                canonicalUrl: null,
                isIndexable: false,
                depth: 1,
                inlinksCount: 0,
                outlinksCount: 0,
                isInSitemap: false,
                isOrphan: true
            ),
        ];

        $csv = $this->exporter->exportPagesCsv($pages);

        // Verify UTF-8 BOM is present at byte 0
        $this->assertStringStartsWith(MultiPageCsvExporter::UTF8_BOM, $csv);

        // Verify Header contains required columns
        $this->assertStringContainsString('URL', $csv);
        $this->assertStringContainsString('Durum Kodu', $csv);
        $this->assertStringContainsString('Başlık', $csv);
        $this->assertStringContainsString('Kanonik URL', $csv);
        $this->assertStringContainsString('Tıklama Derinliği', $csv);
        $this->assertStringContainsString('Yetim Sayfa', $csv);

        // Verify Turkish characters preserved
        $this->assertStringContainsString('Türkçe Başlık & Özel Karakterler (ç, ğ, ı, ö, ş, ü)', $csv);

        // Verify formula injection was neutralized with leading single quote
        $this->assertStringContainsString("'=2+5*cmd", $csv);
    }

    public function testExportsFindingsCsv(): void
    {
        $findings = [
            new Finding(
                ruleId: 'TECH_STATUS_404',
                ruleVersion: '1.0',
                status: FindingStatus::FAIL,
                severity: FindingSeverity::HIGH,
                confidence: Confidence::HIGH,
                dataSource: 'crawler',
                observedAt: '2026-11-05T12:00:00Z',
                affectedUrls: ['https://example.com/broken-page', 'https://example.com/dead-link'],
                evidence: [['http_status' => 404]]
            ),
        ];

        $csv = $this->exporter->exportFindingsCsv($findings);

        $this->assertStringStartsWith(MultiPageCsvExporter::UTF8_BOM, $csv);
        $this->assertStringContainsString('Kural Kimliği', $csv);
        $this->assertStringContainsString('TECH_STATUS_404', $csv);
        $this->assertStringContainsString('https://example.com/broken-page', $csv);
        $this->assertStringContainsString('https://example.com/dead-link', $csv);
    }

    public function testStreamsPagesToResource(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);

        $pages = [
            new SiteWidePageItem('https://example.com/stream-1', 200, title: 'Streamed 1'),
            new SiteWidePageItem('https://example.com/stream-2', 200, title: 'Streamed 2'),
        ];

        $this->exporter->exportPagesToStream($pages, $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertIsString($output);
        $this->assertStringStartsWith(MultiPageCsvExporter::UTF8_BOM, $output);
        $this->assertStringContainsString('https://example.com/stream-1', $output);
        $this->assertStringContainsString('https://example.com/stream-2', $output);
    }
}

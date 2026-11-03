<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/** TEST: Yalnız repo fixture/sözleşmeleri; crawler veya gerçek API testi değildir. */
final class FixtureContractTest extends TestCase
{
    public function testDemoFindingUsesApprovedFieldNamesAndEvidenceList(): void
    {
        $demo = $this->loadJson('examples/analysis-response.example.json');
        $schema = $this->loadJson('examples/finding.schema.json');
        self::assertTrue($demo['_demo']);
        self::assertIsArray($demo['findings']);
        self::assertNotEmpty($demo['findings']);
        self::assertIsArray($schema['required']);

        foreach ($demo['findings'] as $finding) {
            self::assertIsArray($finding);
            foreach ($schema['required'] as $field) {
                self::assertIsString($field);
                self::assertArrayHasKey($field, $finding);
            }
            self::assertIsString($finding['data_source']);
            self::assertArrayNotHasKey('source', $finding);
            self::assertIsArray($finding['evidence']);
            self::assertTrue(array_is_list($finding['evidence']));
            foreach ($finding['evidence'] as $evidence) {
                self::assertIsArray($evidence);
            }
        }
    }

    public function testNoDataFixtureIsUnknownWithoutAnInventedScoreOrEvidence(): void
    {
        $fixture = $this->loadJson('tests/Fixtures/unknown-analysis.json');
        self::assertTrue($fixture['_demo']);
        self::assertNull($fixture['scores']);
        self::assertIsArray($fixture['findings']);
        self::assertCount(1, $fixture['findings']);
        $finding = $fixture['findings'][0];
        self::assertIsArray($finding);
        self::assertSame('UNKNOWN', $finding['status']);
        self::assertSame([], $finding['evidence']);
        self::assertSame('test-fixture', $finding['data_source']);
    }

    public function testTranslationKeysAndNamedParametersMatchInBothLanguages(): void
    {
        $turkish = $this->loadJson('locales/tr-TR.example.json');
        $english = $this->loadJson('locales/en-US.example.json');
        self::assertSame(array_keys($turkish), array_keys($english));

        foreach ($turkish as $key => $text) {
            self::assertIsString($text);
            self::assertIsString($english[$key]);
            preg_match_all('/\{\w+\}/u', $text, $trParameters);
            preg_match_all('/\{\w+\}/u', $english[$key], $enParameters);
            self::assertSame($trParameters[0], $enParameters[0]);
        }
    }

    /** @return array<string, mixed> */
    private function loadJson(string $relativePath): array
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        if ($content === false) {
            throw new RuntimeException('TEST fixture okunamadı: ' . $relativePath);
        }

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('TEST fixture bir JSON nesnesi olmalı.');
        }

        return $decoded;
    }
}

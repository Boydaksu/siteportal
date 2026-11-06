<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Core\Rendering\DomDiffCalculator;

final class DomDiffCalculatorTest extends TestCase
{
    private HtmlParser $parser;
    private DomDiffCalculator $calculator;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
        $this->calculator = new DomDiffCalculator();
    }

    public function testCalculatesEqualDocumentsCorrectly(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Sabit Başlık</title>
    <meta name="description" content="Açıklama metni">
</head>
<body>
    <h1>Ana Başlık</h1>
    <p>Bu bir test paragrafıdır ve statik içerik barındırır. Toplamda yeterli kelime içerir.</p>
    <a href="/link-1">Bağlantı 1</a>
    <a href="/link-2">Bağlantı 2</a>
</body>
</html>
HTML;

        $rawDoc = $this->parser->parse($html, 'https://example.com/test');
        $renderedDoc = $this->parser->parse($html, 'https://example.com/test');

        $result = $this->calculator->calculate($rawDoc, $renderedDoc);

        self::assertSame($rawDoc->getWordCount(), $result->getRawWordCount());
        self::assertSame($renderedDoc->getWordCount(), $result->getRenderedWordCount());
        self::assertSame(0, $result->getWordCountDelta());
        self::assertSame(1.0, $result->getWordCountRatio());
        self::assertSame([], $result->getNewLinks());
        self::assertSame([], $result->getRemovedLinks());
        self::assertSame([], $result->getNewHeadings());
        self::assertFalse($result->isTitleChanged());
        self::assertFalse($result->isMetaDescriptionChanged());
        self::assertFalse($result->isHydrationHeavy());

        $array = $result->toArray();
        self::assertArrayHasKey('raw_word_count', $array);
        self::assertArrayHasKey('rendered_word_count', $array);
        self::assertArrayHasKey('word_count_delta', $array);
        self::assertArrayHasKey('is_hydration_heavy', $array);
    }

    public function testDetectsHeavyHydrationWhenEmptyShellHydratesContent(): void
    {
        // Ham HTML: Sadece 3 kelime içeren SPA yükleme iskeleti
        $rawHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Yükleniyor...</title>
</head>
<body>
    <div id="root">Uygulama yükleniyor lütfen bekleyin...</div>
</body>
</html>
HTML;

        // Render edilmiş DOM: 60+ kelimelik dinamik içerik, yeni linkler ve başlıklar
        $words = implode(' ', array_fill(0, 70, 'dinamik-kelime'));
        $renderedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Dinamik React Sayfası - Ürün Detayı</title>
    <meta name="description" content="İstemci tarafında enjekte edilen meta açıklama">
</head>
<body>
    <div id="root">
        <h1>Ürün Başlığı</h1>
        <h2>Özellikler Listesi</h2>
        <p>{$words}</p>
        <a href="/kategori/elektronik">Kategori</a>
        <a href="/sepet/ekle">Sepete Ekle</a>
    </div>
</body>
</html>
HTML;

        $rawDoc = $this->parser->parse($rawHtml, 'https://example.com/spa');
        $renderedDoc = $this->parser->parse($renderedHtml, 'https://example.com/spa');

        $result = $this->calculator->calculate($rawDoc, $renderedDoc);

        self::assertTrue($result->isHydrationHeavy());
        self::assertTrue($result->isTitleChanged());
        self::assertTrue($result->isMetaDescriptionChanged());
        self::assertGreaterThan(0, $result->getWordCountDelta());
        self::assertCount(2, $result->getNewLinks());
        self::assertContains('/kategori/elektronik', $result->getNewLinks());
        self::assertContains('/sepet/ekle', $result->getNewLinks());
        self::assertCount(2, $result->getNewHeadings());
    }

    public function testDetectsHeavyHydrationWhenExpansionExceedsThreshold(): void
    {
        // 50 kelimelik statik içerik
        $rawWords = implode(' ', array_fill(0, 50, 'statik-kelime'));
        $rawHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Başlık</title></head>
<body><p>{$rawWords}</p></body>
</html>
HTML;

        // 100 kelimelik içerik (oran = 2.0 >= 1.4 eşiği)
        $renderedWords = implode(' ', array_fill(0, 100, 'render-kelime'));
        $renderedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Başlık</title></head>
<body><p>{$renderedWords}</p></body>
</html>
HTML;

        $rawDoc = $this->parser->parse($rawHtml, 'https://example.com/expansion');
        $renderedDoc = $this->parser->parse($renderedHtml, 'https://example.com/expansion');

        $result = $this->calculator->calculate($rawDoc, $renderedDoc);

        self::assertTrue($result->isHydrationHeavy());
        self::assertGreaterThanOrEqual(1.4, $result->getWordCountRatio());
    }

    public function testDoesNotFlagAsHydrationHeavyWhenDifferenceIsMinor(): void
    {
        // 60 kelimelik statik içerik
        $rawWords = implode(' ', array_fill(0, 60, 'kelime'));
        $rawHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Başlık</title></head>
<body><p>{$rawWords}</p></body>
</html>
HTML;

        // 66 kelimelik içerik (+10%, oran = 1.1 < 1.4)
        $renderedWords = implode(' ', array_fill(0, 66, 'kelime'));
        $renderedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Başlık</title></head>
<body><p>{$renderedWords}</p></body>
</html>
HTML;

        $rawDoc = $this->parser->parse($rawHtml, 'https://example.com/minor');
        $renderedDoc = $this->parser->parse($renderedHtml, 'https://example.com/minor');

        $result = $this->calculator->calculate($rawDoc, $renderedDoc);

        self::assertFalse($result->isHydrationHeavy());
    }

    public function testDetectsRemovedLinks(): void
    {
        $rawHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <a href="/eski-link">Eski Link</a>
    <a href="/kalici-link">Kalıcı Link</a>
</body>
</html>
HTML;

        $renderedHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <a href="/kalici-link">Kalıcı Link</a>
</body>
</html>
HTML;

        $rawDoc = $this->parser->parse($rawHtml, 'https://example.com/links');
        $renderedDoc = $this->parser->parse($renderedHtml, 'https://example.com/links');

        $result = $this->calculator->calculate($rawDoc, $renderedDoc);

        self::assertSame(['/eski-link'], $result->getRemovedLinks());
        self::assertSame([], $result->getNewLinks());
    }
}

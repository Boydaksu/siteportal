<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Parser;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Parser\HtmlParser;
use SitePortal\Tests\Fixtures\MobileA11yFixtures;

final class MobileA11yParserTest extends TestCase
{
    private HtmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
    }

    public function testExtractsValidViewportMetaTag(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::validMobileViewportHtml());

        self::assertSame('width=device-width, initial-scale=1.0', $doc->getViewport());
    }

    public function testExtractsNullWhenViewportMissing(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::missingViewportHtml());

        self::assertNull($doc->getViewport());
    }

    public function testExtractsRestrictiveScalingViewport(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::restrictiveScalingViewportHtml());

        self::assertSame(
            'width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0',
            $doc->getViewport()
        );
    }

    public function testExtractsAllFormInputsAndAccessibleLabels(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::validFormLabelsHtml());
        $inputs = $doc->getFormInputs();

        // Toplam form kontrolü: email, password, search, bio, country, hidden, submit, button, reset = 9
        self::assertCount(9, $inputs);

        // 1. label[for]
        $email = $inputs[0];
        self::assertSame('input', $email['tag']);
        self::assertSame('user-email', $email['id']);
        self::assertSame('email', $email['name']);
        self::assertSame('email', $email['type']);
        self::assertTrue($email['has_label']);
        self::assertSame('E-posta Adresi', $email['label_text']);
        self::assertSame('label_for', $email['label_source']);
        self::assertFalse($email['is_excluded']);

        // 2. wrapped label
        $password = $inputs[1];
        self::assertTrue($password['has_label']);
        self::assertSame('Şifreniz', $password['label_text']);
        self::assertSame('wrapped_label', $password['label_source']);
        self::assertFalse($password['is_excluded']);

        // 3. aria-label
        $search = $inputs[2];
        self::assertTrue($search['has_label']);
        self::assertSame('Site İçi Arama', $search['label_text']);
        self::assertSame('aria-label', $search['label_source']);
        self::assertFalse($search['is_excluded']);

        // 4. aria-labelledby
        $bio = $inputs[3];
        self::assertSame('textarea', $bio['tag']);
        self::assertTrue($bio['has_label']);
        self::assertSame('Kısa Biyografi', $bio['label_text']);
        self::assertSame('aria-labelledby', $bio['label_source']);
        self::assertFalse($bio['is_excluded']);

        // 5. title fallback
        $country = $inputs[4];
        self::assertSame('select', $country['tag']);
        self::assertTrue($country['has_label']);
        self::assertSame('Ülke Seçiniz', $country['label_text']);
        self::assertSame('title', $country['label_source']);
        self::assertFalse($country['is_excluded']);

        // 6. Hariç tutulan kontroller
        $hidden = $inputs[5];
        self::assertTrue($hidden['is_excluded']);
        $submit = $inputs[6];
        self::assertTrue($submit['is_excluded']);
        $button = $inputs[7];
        self::assertTrue($button['is_excluded']);
        $reset = $inputs[8];
        self::assertTrue($reset['is_excluded']);

        // Eksik etiketli kontrol olmamalı
        self::assertEmpty($doc->getFormInputsMissingLabels());
    }

    public function testDetectsMissingFormLabelsAccurately(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::missingFormLabelsHtml());
        $missing = $doc->getFormInputsMissingLabels();

        // username etiketli, phone / gender / notes etiketsiz, source hidden (hariç)
        self::assertCount(3, $missing);

        $tagsAndNames = array_map(static fn (array $i): string => $i['tag'] . ':' . ($i['name'] ?? ''), $missing);
        self::assertContains('input:phone', $tagsAndNames);
        self::assertContains('select:gender', $tagsAndNames);
        self::assertContains('textarea:notes', $tagsAndNames);
    }

    public function testReturnsEmptyInputsWhenNoFormPresent(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::noFormHtml());

        self::assertEmpty($doc->getFormInputs());
        self::assertEmpty($doc->getFormInputsMissingLabels());
    }

    public function testRecognizesDecorativeImagesAndExcludesThemFromMissingAlt(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::decorativeImagesHtml());
        $images = $doc->getImages();

        self::assertCount(6, $images);

        // Eksik alt listesinde sadece /chart-statistics.png olmalı
        // role="presentation", role="none", aria-hidden="true" ve alt="" hariç tutulmalıdır
        $missingAlt = $doc->getImagesMissingAlt();
        self::assertCount(1, $missingAlt);
        self::assertSame('/chart-statistics.png', $missingAlt[0]['src']);
    }

    public function testToArrayIncludesMobileAndA11yMetrics(): void
    {
        $doc = $this->parser->parse(MobileA11yFixtures::validFormLabelsHtml());
        $array = $doc->toArray();

        self::assertArrayHasKey('viewport', $array);
        self::assertSame('width=device-width, initial-scale=1.0', $array['viewport']);
        self::assertArrayHasKey('form_inputs_count', $array);
        self::assertSame(9, $array['form_inputs_count']);
        self::assertArrayHasKey('form_inputs_missing_labels_count', $array);
        self::assertSame(0, $array['form_inputs_missing_labels_count']);
    }
}

<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\I18n;

final class I18nTest extends TestCase
{
    private I18n $i18n;

    protected function setUp(): void
    {
        $this->i18n = new I18n();
    }

    public function testDefaultLocaleIsTurkish(): void
    {
        self::assertSame('tr-TR', $this->i18n->getLocale());
        self::assertSame('tr-TR', $this->i18n->getDefaultLocale());
    }

    public function testTranslatesInDefaultAndSelectedLocale(): void
    {
        self::assertSame('SitePortal', $this->i18n->t('app.name'));

        $this->i18n->setLocale('tr-TR');
        self::assertSame('Ana Sayfa', $this->i18n->t('nav.home'));

        $this->i18n->setLocale('en-US');
        self::assertSame('Home', $this->i18n->t('nav.home'));
    }

    public function testInterpolatesNamedParameters(): void
    {
        $this->i18n->setLocale('tr-TR');
        $tr = $this->i18n->t('seo.report.coverage', ['count' => 42]);
        self::assertSame('42 sayfa analiz edildi', $tr);

        $this->i18n->setLocale('en-US');
        $en = $this->i18n->t('seo.report.coverage', ['count' => 42]);
        self::assertSame('42 pages analyzed', $en);
    }

    public function testReturnsKeyIfTranslationNotFound(): void
    {
        self::assertSame('unknown.key.test', $this->i18n->t('unknown.key.test'));
    }

    public function testTrAndEnTranslationKeysAndParametersMatchStrictly(): void
    {
        $trMessages = $this->i18n->getMessages('tr-TR');
        $enMessages = $this->i18n->getMessages('en-US');

        self::assertNotEmpty($trMessages);
        self::assertNotEmpty($enMessages);

        $trKeys = array_keys($trMessages);
        $enKeys = array_keys($enMessages);
        sort($trKeys);
        sort($enKeys);

        self::assertSame($trKeys, $enKeys, 'tr-TR ve en-US anahtarları birebir aynı olmalıdır.');

        foreach ($trMessages as $key => $trText) {
            $enText = $enMessages[$key];

            preg_match_all('/\{(\w+)\}/', $trText, $trParams);
            preg_match_all('/\{(\w+)\}/', $enText, $enParams);

            sort($trParams[1]);
            sort($enParams[1]);

            self::assertSame(
                $trParams[1],
                $enParams[1],
                "{$key} anahtarı için parametreler eşleşmiyor."
            );
        }
    }
}

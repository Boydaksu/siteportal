<?php

declare(strict_types=1);

namespace SitePortal\Tests\Fixtures;

final class HtmlFixtures
{
    /**
     * Eksiksiz, geçerli, modern HTML5 sayfası.
     */
    public static function getValidFullHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr-TR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SitePortal — Bağımsız SEO Analiz ve Denetim Aracı</title>
    <meta name="description"
          content="SitePortal ile web sitenizin teknik ve içerik SEO durumunu bağımsız analiz edin.">
    <meta name="robots" content="index, follow, max-snippet:150, max-image-preview:large">
    <link rel="canonical" href="https://example.com/rehber/seo">
    <meta property="og:title" content="SitePortal SEO Rehberi">
    <meta property="og:description" content="Kapsamlı SEO denetim ve optimizasyon kılavuzu.">
    <meta property="og:image" content="https://example.com/og-image.jpg">
    <meta property="og:url" content="https://example.com/rehber/seo">
    <meta property="og:type" content="article">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SitePortal SEO Analizi">
    <meta name="twitter:description" content="Şeffaf SEO raporları">
    <meta name="twitter:image" content="https://example.com/twitter-card.jpg">
    <style>
        body { font-family: sans-serif; }
        .hidden { display: none; }
    </style>
    <script>
        var fakeData = "<h1>Sahte H1 Metni</h1><title>Sahte Title</title>";
    </script>
</head>
<body>
    <header>
        <img src="/images/logo.png" alt="SitePortal Logo" width="200" height="50" loading="eager">
        <img src="/images/decorative-line.svg" alt="" role="presentation">
        <img src="/images/banner-no-alt.jpg" title="Açıklamasız Banner">
        <nav>
            <a href="/ozellikler">Özellikler</a>
            <a href="hakkimizda/">Hakkımızda</a>
            <a href="#icerik">İçeriğe Geç</a>
        </nav>
    </header>
    <main id="icerik">
        <h1>SitePortal SEO Rehberi: Ağaç, Çiçek ve Işık</h1>
        <p>Arama motoru optimizasyonu teknik ve içerik uyumu gerektirir.
        Şiirsel anlatım ile öykü birleştiğinde kullanıcı deneyimi güçlenir.</p>

        <h2>Teknik Denetim Kriterleri</h2>
        <p>Tarama bütçesi ve robots direktifleri doğru kurgulanmalıdır.</p>
        <h3>Robots ve Meta Direktifleri</h3>
        <p>noindex ve nofollow kuralları şeffaf test edilmelidir.</p>

        <h2>Sayfa İçi Optimizasyon</h2>
        <p>Başlık etiketleri ve alt metinler eksiksiz olmalıdır.</p>
        <h3>Başlık Hiyerarşisi</h3>
        <h4>Alt Seviye Başlık</h4>

        <section class="links">
            <a href="https://github.com/Boydaksu/siteportal"
               rel="noopener noreferrer" target="_blank">GitHub Depomuz</a>
            <a href="https://external-partner.com" rel="nofollow ugc">Sponsorlu Bağlantı</a>
            <a href="mailto:destek@siteportal.com">Destek E-posta</a>
            <a href="tel:+905551234567">Telefon Hattı</a>
            <a href="javascript:void(0)">Etkileşim Butonu</a>
        </section>
    </main>
    <footer>
        <p>© 2026 SitePortal. Tüm hakları saklıdır.</p>
    </footer>
    <script>
        console.log("Footer script execution");
    </script>
</body>
</html>
HTML;
    }

    /**
     * Bozuk, kapatılmamış etiketli, sözdizimi hatalı HTML.
     */
    public static function getMalformedHtml(): string
    {
        return <<<'HTML'
<<<>>>&&&<foo invalid="1">
<div><p><span>Ayrıştırıcı test metni<b>kalın metin
<h1>Kapatılmamış Başlık
<a href="/bozuk-link">Kapatılmamış Link
<img src="test.jpg"
HTML;
    }

    /**
     * Yinelenen title, description, canonical ve birden fazla h1 içeren HTML.
     */
    public static function getDuplicateTagsHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Birinci Başlık</title>
    <title>İkinci Başlık</title>
    <meta name="description" content="Birinci Açıklama">
    <meta name="description" content="İkinci Açıklama">
    <link rel="canonical" href="https://example.com/canonical-1">
    <link rel="canonical" href="https://example.com/canonical-2">
</head>
<body>
    <h1>Birinci H1 Başlığı</h1>
    <p>Paragraf metni</p>
    <h1>İkinci H1 Başlığı</h1>
    <p>Başka paragraf</p>
    <h1>Üçüncü H1 Başlığı</h1>
</body>
</html>
HTML;
    }

    /**
     * ISO-8859-9 kodlamasında Türkçe karakterler içeren HTML.
     */
    public static function getIso88599Html(): string
    {
        $utf8 = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-9">
    <title>Türkçe Başlık: Şeker, Çağlayan, Ördek</title>
    <meta name="description" content="Işık ve çiçek ile üzüm dolu bir bahçe.">
</head>
<body>
    <h1>Şiir ve Öykü: Ağaçlar</h1>
    <p>Ilık bir rüzgâr eşliğinde ılık sular akıyor.</p>
</body>
</html>
HTML;

        return mb_convert_encoding($utf8, 'ISO-8859-9', 'UTF-8');
    }

    /**
     * XXE (XML External Entity) saldırı vektörü içeren HTML.
     */
    public static function getXxePayloadHtml(): string
    {
        return <<<'HTML'
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE test [
    <!ENTITY % xxe SYSTEM "file:///etc/passwd">
    <!ENTITY % xxe2 SYSTEM "http://169.254.169.254/latest/meta-data/">
    %xxe;
    %xxe2;
]>
<html>
<head><title>XXE Test</title></head>
<body><h1>XXE Test Body</h1></body>
</html>
HTML;
    }

    /**
     * <base href="..."> etiketi içeren HTML.
     */
    public static function getBaseHrefHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <base href="https://cdn.example.com/app/v2/">
    <title>Base Href Test</title>
    <link rel="canonical" href="canonical-page">
</head>
<body>
    <a href="about.html">Hakkında</a>
    <a href="/root-page">Kök Sayfa</a>
    <img src="logo.png" alt="Logo">
</body>
</html>
HTML;
    }

    /**
     * Robots "none" (noindex, nofollow) direktifi içeren HTML.
     */
    public static function getRobotsNoneHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Gizli Sayfa</title>
    <meta name="robots" content="none">
</head>
<body>
    <h1>İndekslenmemesi Gereken Sayfa</h1>
</body>
</html>
HTML;
    }

    /**
     * Başlıksız, meta verisiz boş HTML.
     */
    public static function getEmptyDocumentHtml(): string
    {
        return '<!DOCTYPE html><html><head></head><body></body></html>';
    }
}

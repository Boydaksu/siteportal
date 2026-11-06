<?php

declare(strict_types=1);

namespace SitePortal\Tests\Fixtures;

/**
 * HTML fixtures for Structured Data tests (JSON-LD, Microdata, RDFa).
 * Includes valid, syntax-errored, invalid nested, and missing-field scenarios.
 */
final class StructuredDataFixtures
{
    /**
     * Valid JSON-LD Article with complete required and recommended properties.
     */
    public static function validJsonLdArticle(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Haber Başlığı</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "Türkiye'de Teknoloji Yatırımları Hız Kesmiyor",
        "image": ["https://example.com/photos/1x1/photo.jpg"],
        "datePublished": "2026-11-05T08:00:00+08:00",
        "dateModified": "2026-11-05T09:20:00+08:00",
        "author": [{
            "@type": "Person",
            "name": "Ahmet Yılmaz",
            "url": "https://example.com/profile/ahmet"
        }]
    }
    </script>
</head>
<body>
    <h1>Türkiye'de Teknoloji Yatırımları Hız Kesmiyor</h1>
</body>
</html>
HTML;
    }

    /**
     * Valid JSON-LD Product with offers and rating.
     */
    public static function validJsonLdProduct(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Akıllı Telefon Ürün Sayfası</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "Süper Akıllı Telefon Pro",
        "image": "https://example.com/phone.jpg",
        "description": "En gelişmiş özelliklere sahip akıllı telefon.",
        "brand": {
            "@type": "Brand",
            "name": "TechCorp"
        },
        "offers": {
            "@type": "Offer",
            "url": "https://example.com/phone",
            "priceCurrency": "TRY",
            "price": "35000",
            "availability": "https://schema.org/InStock"
        }
    }
    </script>
</head>
<body>
    <h1>Süper Akıllı Telefon Pro</h1>
</body>
</html>
HTML;
    }

    /**
     * Valid JSON-LD with nested @graph holding multiple entities (BreadcrumbList + Organization).
     */
    public static function validJsonLdGraph(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Kategori Sayfası</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "BreadcrumbList",
                "itemListElement": [
                    {
                        "@type": "ListItem",
                        "position": 1,
                        "name": "Ana Sayfa",
                        "item": "https://example.com"
                    },
                    {
                        "@type": "ListItem",
                        "position": 2,
                        "name": "Elektronik",
                        "item": "https://example.com/elektronik"
                    }
                ]
            },
            {
                "@type": "Organization",
                "name": "Örnek Mağaza",
                "url": "https://example.com"
            }
        ]
    }
    </script>
</head>
<body>
    <h1>Elektronik</h1>
</body>
</html>
HTML;
    }

    /**
     * Malformed JSON syntax (unclosed curly brace, invalid JSON-LD).
     */
    public static function invalidSyntaxMalformedJson(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Bozuk JSON Sayfası</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "Bozuk JSON Testi",
        "incomplete_array": [1, 2, 3
    }
    </script>
</head>
<body>
    <h1>Bozuk JSON</h1>
</body>
</html>
HTML;
    }

    /**
     * Invalid nested JSON-LD structure (syntax error in deeply nested sub-object).
     */
    public static function invalidSyntaxNestedMalformedJson(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>İç İçe Bozuk JSON-LD</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Product",
                "name": "Ürün 1",
                "offers": {
                    "@type": "Offer",
                    "price": "100.00",
                    "seller": {
                        "@type": "Organization",
                        "name": "Satıcı",,,
                    }
                }
            }
        ]
    }
    </script>
</head>
<body>
    <h1>İç İçe Bozuk Veri</h1>
</body>
</html>
HTML;
    }

    /**
     * Invalid context (points to non-Schema.org domain or missing context).
     */
    public static function invalidContextJsonLd(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Geçersiz Context Sayfası</title>
    <script type="application/ld+json">
    {
        "@context": "http://unauthorized-custom-vocab.org/schemas",
        "@type": "CustomEntity",
        "customField": "Değer"
    }
    </script>
</head>
<body>
    <h1>Geçersiz Context</h1>
</body>
</html>
HTML;
    }

    /**
     * Missing mandatory fields for Google Rich Result (Product missing required 'name').
     */
    public static function missingRequiredFieldsProduct(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Eksik Alanlı Ürün</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "description": "İsmi olmayan bir ürün",
        "sku": "SKU12345"
    }
    </script>
</head>
<body>
    <h1>Eksik Alan</h1>
</body>
</html>
HTML;
    }

    /**
     * Valid Schema.org markup, but NO Google Rich Result eligible types (only Thing or WebPage).
     */
    public static function noRichResultTypeJsonLd(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Rich Result Olmayan Sayfa</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Kullanım Koşulları",
        "description": "Sitemizin kullanım koşulları ve gizlilik sözleşmesi."
    }
    </script>
</head>
<body>
    <h1>Kullanım Koşulları</h1>
</body>
</html>
HTML;
    }

    /**
     * Valid HTML5 Microdata on page elements.
     */
    public static function validMicrodata(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Microdata Ürün</title>
</head>
<body>
    <div itemscope itemtype="https://schema.org/Product">
        <h1 itemprop="name">Microdata Akıllı Saat</h1>
        <span itemprop="description">Gelişmiş nabız ve adım sayar saat.</span>
        <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <span itemprop="price">1500</span>
            <span itemprop="priceCurrency">TRY</span>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Invalid Microdata (itemscope present, but itemtype missing).
     */
    public static function invalidMicrodataNoType(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Eksik Type Microdata</title>
</head>
<body>
    <div itemscope>
        <span itemprop="title">Türsüz başlık</span>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Valid RDFa in HTML.
     */
    public static function validRdfa(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr" vocab="https://schema.org/">
<head>
    <title>RDFa Makale</title>
</head>
<body>
    <article typeof="Article">
        <h1 property="headline">RDFa ile Yapılandırılmış Makale</h1>
        <p property="author">Zeynep Kaya</p>
    </article>
</body>
</html>
HTML;
    }

    /**
     * HTML with no structured data at all.
     */
    public static function noStructuredDataHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <title>Düz Sayfa</title>
</head>
<body>
    <h1>Yapılandırılmış Veri Yok</h1>
    <p>Bu sayfada JSON-LD, Microdata veya RDFa bulunmuyor.</p>
</body>
</html>
HTML;
    }
}

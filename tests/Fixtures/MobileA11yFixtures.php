<?php

declare(strict_types=1);

namespace SitePortal\Tests\Fixtures;

final class MobileA11yFixtures
{
    /**
     * Geçerli mobil viewport meta etiketli HTML.
     */
    public static function validMobileViewportHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Geçerli Mobil Viewport</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>Mobil Uyumlu Sayfa</h1>
    <p>İçerik buraya gelecek.</p>
</body>
</html>
HTML;
    }

    /**
     * Viewport meta etiketi eksik HTML.
     */
    public static function missingViewportHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Eksik Viewport Sayfası</title>
</head>
<body>
    <h1>Masaüstü Sabit Sayfa</h1>
</body>
</html>
HTML;
    }

    /**
     * user-scalable=no ve maximum-scale=1 ile yakınlaştırmayı engelleyen (WCAG 1.4.4 ihlali) HTML.
     */
    public static function restrictiveScalingViewportHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Kısıtlayıcı Viewport Sayfası</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
</head>
<body>
    <h1>Kullanıcı Yakınlaştırması Engellenmiş Sayfa</h1>
</body>
</html>
HTML;
    }

    /**
     * width=device-width veya initial-scale içermeyen geçersiz viewport HTML.
     */
    public static function invalidViewportHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Geçersiz Viewport Sayfası</title>
    <meta name="viewport" content="height=device-height">
</head>
<body>
    <h1>Hatalı Viewport</h1>
</body>
</html>
HTML;
    }

    /**
     * Tüm form girdilerinin erişilebilir etiketlere sahip olduğu geçerli HTML.
     */
    public static function validFormLabelsHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Erişilebilir Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <form action="/login" method="post">
        <!-- 1. label[for] -->
        <label for="user-email">E-posta Adresi</label>
        <input type="email" id="user-email" name="email">

        <!-- 2. sarmalayan <label> -->
        <label>
            Şifreniz
            <input type="password" name="password">
        </label>

        <!-- 3. aria-label -->
        <input type="search" name="q" aria-label="Site İçi Arama">

        <!-- 4. aria-labelledby -->
        <span id="bio-title">Kısa Biyografi</span>
        <textarea id="bio-input" name="bio" aria-labelledby="bio-title"></textarea>

        <!-- 5. title fallback -->
        <select name="country" title="Ülke Seçiniz">
            <option value="TR">Türkiye</option>
            <option value="US">ABD</option>
        </select>

        <!-- Hariç tutulan girdiler (etiket gerektirmez) -->
        <input type="hidden" name="csrf" value="token123">
        <input type="submit" value="Giriş Yap">
        <input type="button" value="Vazgeç">
        <input type="reset" value="Temizle">
    </form>
</body>
</html>
HTML;
    }

    /**
     * Bazı form girdilerinde etiketlerin eksik olduğu HTML.
     */
    public static function missingFormLabelsHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Erişilebilirlik Hatalı Form</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <form action="/register" method="post">
        <!-- Etiketi olan input -->
        <label for="username">Kullanıcı Adı</label>
        <input type="text" id="username" name="username">

        <!-- Etiketi EKSİK input -->
        <input type="text" id="phone" name="phone">

        <!-- Etiketi EKSİK select -->
        <select name="gender">
            <option value="m">Erkek</option>
            <option value="f">Kadın</option>
        </select>

        <!-- Etiketi EKSİK textarea -->
        <textarea name="notes"></textarea>

        <!-- Hariç tutulan input (hata sayılmaz) -->
        <input type="hidden" name="source" value="landing">
    </form>
</body>
</html>
HTML;
    }

    /**
     * Form öğesi bulunmayan HTML.
     */
    public static function noFormHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Formsuz Sayfa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>İçerik Başlığı</h1>
    <p>Bu sayfada hiçbir form girdisi bulunmamaktadır.</p>
</body>
</html>
HTML;
    }

    /**
     * Dekoratif (role="presentation", role="none", aria-hidden="true") ve anlamlı görseller içeren HTML.
     */
    public static function decorativeImagesHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Görsel Erişilebilirliği</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <!-- Dekoratif: role="presentation", alt yok ama eksik sayılmamalı -->
    <img src="/bg-decoration.png" role="presentation">

    <!-- Dekoratif: role="none", alt yok ama eksik sayılmamalı -->
    <img src="/pattern.png" role="none">

    <!-- Dekoratif: aria-hidden="true", alt yok ama eksik sayılmamalı -->
    <img src="/icon-visual.png" aria-hidden="true">

    <!-- Dekoratif: alt="" standart WCAG, eksik sayılmamalı -->
    <img src="/spacer.gif" alt="">

    <!-- Anlamlı ve alt metni olan görsel -->
    <img src="/logo.png" alt="SitePortal Logosu">

    <!-- Anlamlı fakat alt metni EKSİK görsel (hata sayılmalı) -->
    <img src="/chart-statistics.png">
</body>
</html>
HTML;
    }
}

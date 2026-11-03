<?php

declare(strict_types=1);

namespace SitePortal\Controllers;

use SitePortal\Core\Config;
use SitePortal\Core\Request;
use SitePortal\Core\Response;

final class StaticFileController
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    public function robots(Request $request): Response
    {
        $baseUrl = (string) $this->config->get('app.url', 'http://localhost:8080');

        $content = "User-agent: *\n" .
            "Disallow: /api/\n" .
            "Sitemap: {$baseUrl}/sitemap.xml\n";

        return Response::text($content, 200);
    }

    public function sitemap(Request $request): Response
    {
        $baseUrl = (string) $this->config->get('app.url', 'http://localhost:8080');
        $date = date('Y-m-d');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" .
            "  <url>\n" .
            "    <loc>{$baseUrl}/</loc>\n" .
            "    <lastmod>{$date}</lastmod>\n" .
            "    <changefreq>daily</changefreq>\n" .
            "    <priority>1.0</priority>\n" .
            "  </url>\n" .
            "  <url>\n" .
            "    <loc>{$baseUrl}/health</loc>\n" .
            "    <lastmod>{$date}</lastmod>\n" .
            "    <changefreq>hourly</changefreq>\n" .
            "    <priority>0.5</priority>\n" .
            "  </url>\n" .
            "</urlset>\n";

        return Response::xml($xml, 200);
    }
}

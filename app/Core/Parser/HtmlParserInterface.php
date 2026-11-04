<?php

declare(strict_types=1);

namespace SitePortal\Core\Parser;

use SitePortal\Core\Parser\Exceptions\ParserException;

/**
 * HtmlParserInterface — HTML ve belge ayrıştırma sözleşmesi.
 */
interface HtmlParserInterface
{
    /**
     * HTML belgesini ayrıştırır ve tip güvenli ParsedDocument nesnesi döndürür.
     *
     * @param string $html Ham HTML içeriği
     * @param string|null $baseUrl Göreceli bağlantıların ve kanoniklerin çözümlenmesi için temel URL
     * @param string|null $charset Varsa HTTP başlığından gelen karakter kümesi (örn: UTF-8, ISO-8859-9)
     * @return ParsedDocument
     * @throws ParserException Belge boyutu aşıldığında veya kritik ayrıştırma hatasında
     */
    public function parse(string $html, ?string $baseUrl = null, ?string $charset = null): ParsedDocument;
}

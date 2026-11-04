<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Crawler;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Crawler\Scope\RegistrableDomainParser;

final class RegistrableDomainParserTest extends TestCase
{
    private RegistrableDomainParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RegistrableDomainParser();
    }

    public function testStandardGtldDomains(): void
    {
        $this->assertSame('example.com', $this->parser->getRegistrableDomain('example.com'));
        $this->assertSame('example.com', $this->parser->getRegistrableDomain('sub.example.com'));
        $this->assertSame('example.com', $this->parser->getRegistrableDomain('deep.nested.sub.example.com'));
        $this->assertSame('portal.io', $this->parser->getRegistrableDomain('api.portal.io'));
        $this->assertSame('site.org', $this->parser->getRegistrableDomain('www.site.org'));
    }

    public function testTurkishMultiPartCcTlds(): void
    {
        $this->assertSame('example.com.tr', $this->parser->getRegistrableDomain('example.com.tr'));
        $this->assertSame('example.com.tr', $this->parser->getRegistrableDomain('blog.example.com.tr'));
        $this->assertSame('ankara.bel.tr', $this->parser->getRegistrableDomain('ebelediye.ankara.bel.tr'));
        $this->assertSame('metu.edu.tr', $this->parser->getRegistrableDomain('sub.metu.edu.tr'));
        $this->assertSame('tccb.gov.tr', $this->parser->getRegistrableDomain('iletisim.tccb.gov.tr'));
    }

    public function testInternationalMultiPartCcTlds(): void
    {
        $this->assertSame('bbc.co.uk', $this->parser->getRegistrableDomain('www.bbc.co.uk'));
        $this->assertSame('oxford.ac.uk', $this->parser->getRegistrableDomain('sub.oxford.ac.uk'));
        $this->assertSame('company.com.au', $this->parser->getRegistrableDomain('store.company.com.au'));
        $this->assertSame('site.co.jp', $this->parser->getRegistrableDomain('shop.site.co.jp'));
    }

    public function testLocalhostAndIpAddresses(): void
    {
        $this->assertSame('localhost', $this->parser->getRegistrableDomain('localhost'));
        $this->assertSame('127.0.0.1', $this->parser->getRegistrableDomain('127.0.0.1'));
        $this->assertSame('192.168.1.100', $this->parser->getRegistrableDomain('192.168.1.100'));
        $this->assertSame('[::1]', $this->parser->getRegistrableDomain('[::1]'));
    }

    public function testAreSameRegistrableDomain(): void
    {
        $this->assertTrue($this->parser->areSameRegistrableDomain('blog.example.com', 'shop.example.com'));
        $this->assertTrue($this->parser->areSameRegistrableDomain('sub.example.com.tr', 'example.com.tr'));
        $this->assertFalse($this->parser->areSameRegistrableDomain('example.com', 'anotherexample.com'));
        $this->assertFalse($this->parser->areSameRegistrableDomain('example.com.tr', 'example.co.uk'));
    }
}

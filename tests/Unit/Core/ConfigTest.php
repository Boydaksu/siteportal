<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Config;

final class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
    }

    public function testLoadsAppConfigSuccessfully(): void
    {
        self::assertTrue($this->config->has('app.name'));
        self::assertSame('SitePortal', $this->config->get('app.name'));
        self::assertSame('0.1.0', $this->config->get('app.version'));
        self::assertSame('tr-TR', $this->config->get('app.default_locale'));
    }

    public function testReturnsDefaultWhenKeyNotFound(): void
    {
        self::assertFalse($this->config->has('non.existent.key'));
        self::assertSame('custom-default', $this->config->get('non.existent.key', 'custom-default'));
        self::assertNull($this->config->get('non.existent.key'));
    }

    public function testSetAndGetRuntimeConfig(): void
    {
        $this->config->set('custom.setting.foo', 'bar');
        self::assertTrue($this->config->has('custom.setting.foo'));
        self::assertSame('bar', $this->config->get('custom.setting.foo'));
    }

    public function testAllReturnsFullConfigurationArray(): void
    {
        $all = $this->config->all();
        self::assertNotEmpty($all);
        self::assertArrayHasKey('app', $all);
        self::assertSame('SitePortal', $all['app']['name']);
    }
}

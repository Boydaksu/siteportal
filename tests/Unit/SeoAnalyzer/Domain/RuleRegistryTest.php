<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\SeoAnalyzer\Domain;

use PHPUnit\Framework\TestCase;
use SitePortal\SeoAnalyzer\Domain\FindingSeverity;
use SitePortal\SeoAnalyzer\Domain\RuleInterface;
use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpStatusRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectChainRule;

final class RuleRegistryTest extends TestCase
{
    public function testRegistryRegistrationAndRetrieval(): void
    {
        $registry = new RuleRegistry();
        $this->assertSame(0, $registry->count());

        $httpRule = new HttpStatusRule();
        $registry->register($httpRule);

        $this->assertSame(1, $registry->count());
        $this->assertTrue($registry->has('TECH_HTTP_STATUS'));
        $this->assertFalse($registry->has('NON_EXISTENT'));

        $retrieved = $registry->get('TECH_HTTP_STATUS');
        $this->assertSame($httpRule, $retrieved);
        $this->assertNull($registry->get('NON_EXISTENT'));
    }

    public function testRegistryFilterByCategory(): void
    {
        $httpRule = new HttpStatusRule();
        $chainRule = new RedirectChainRule();

        $registry = new RuleRegistry([$httpRule, $chainRule]);

        $this->assertSame(2, $registry->count());
        $technicalRules = $registry->getByCategory('technical');
        $this->assertCount(2, $technicalRules);

        $onPageRules = $registry->getByCategory('on_page');
        $this->assertCount(0, $onPageRules);

        $all = $registry->all();
        $this->assertArrayHasKey('TECH_HTTP_STATUS', $all);
        $this->assertArrayHasKey('TECH_REDIRECT_CHAIN', $all);
    }
}

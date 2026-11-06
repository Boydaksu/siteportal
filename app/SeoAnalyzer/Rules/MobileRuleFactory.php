<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\Mobile\MobileOverflowRule;
use SitePortal\SeoAnalyzer\Rules\Mobile\MobileViewportRule;

final class MobileRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with V1.2 Mobile audit rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new MobileViewportRule(),
            new MobileOverflowRule(),
        ]);
    }
}

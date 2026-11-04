<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Engine;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\OnPageRuleFactory;
use SitePortal\SeoAnalyzer\Rules\TechnicalRuleFactory;

final class V1RuleRegistryFactory
{
    /**
     * Creates and populates a unified RuleRegistry with all 32 V1.0 rules
     * (19 Technical rules + 13 On-Page rules).
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        $technicalRules = TechnicalRuleFactory::createRegistry()->all();
        $onPageRules = OnPageRuleFactory::createRegistry()->all();

        return new RuleRegistry(array_values(array_merge($technicalRules, $onPageRules)));
    }

    public static function create(): RuleRegistryInterface
    {
        return self::createRegistry();
    }
}

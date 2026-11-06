<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Engine;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\AccessibilityRuleFactory;
use SitePortal\SeoAnalyzer\Rules\MobileRuleFactory;
use SitePortal\SeoAnalyzer\Rules\OnPageRuleFactory;
use SitePortal\SeoAnalyzer\Rules\SchemaRuleFactory;
use SitePortal\SeoAnalyzer\Rules\TechnicalRuleFactory;

final class V1RuleRegistryFactory
{
    /**
     * Creates and populates a unified RuleRegistry with all rules.
     * In V1.0 mode: 32 rules (19 Technical rules + 13 On-Page rules).
     * In V1.2 mode: 40 rules (19 Technical + 13 On-Page + 4 Schema + 2 Mobile + 2 Accessibility rules).
     */
    public static function createRegistry(bool $includeV12 = true): RuleRegistryInterface
    {
        $technicalRules = TechnicalRuleFactory::createRegistry()->all();
        $onPageRules = OnPageRuleFactory::createRegistry()->all();

        $rules = array_merge($technicalRules, $onPageRules);

        if ($includeV12) {
            $schemaRules = SchemaRuleFactory::createRegistry()->all();
            $mobileRules = MobileRuleFactory::createRegistry()->all();
            $a11yRules = AccessibilityRuleFactory::createRegistry()->all();
            $rules = array_merge($rules, $schemaRules, $mobileRules, $a11yRules);
        }

        return new RuleRegistry(array_values($rules));
    }

    public static function create(bool $includeV12 = true): RuleRegistryInterface
    {
        return self::createRegistry($includeV12);
    }
}

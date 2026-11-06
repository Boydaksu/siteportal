<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\Technical\TechJsBlockedResourcesRule;
use SitePortal\SeoAnalyzer\Rules\Technical\TechJsContentDeltaRule;

final class JsRenderingRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with V1.2 JavaScript rendering audit rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new TechJsContentDeltaRule(),
            new TechJsBlockedResourcesRule(),
        ]);
    }
}

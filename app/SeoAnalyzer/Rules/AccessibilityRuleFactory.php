<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\Accessibility\A11yContrastRule;
use SitePortal\SeoAnalyzer\Rules\Accessibility\A11yFormLabelRule;

final class AccessibilityRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with V1.2 Accessibility audit rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new A11yFormLabelRule(),
            new A11yContrastRule(),
        ]);
    }
}

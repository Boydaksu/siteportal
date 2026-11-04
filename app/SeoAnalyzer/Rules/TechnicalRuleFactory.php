<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalEmptyRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalMissingRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalMultiConflictRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalOutOfScopeRule;
use SitePortal\SeoAnalyzer\Rules\Technical\CanonicalRedirectRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpsDowngradeRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpsRule;
use SitePortal\SeoAnalyzer\Rules\Technical\HttpStatusRule;
use SitePortal\SeoAnalyzer\Rules\Technical\IndexabilityInferenceRule;
use SitePortal\SeoAnalyzer\Rules\Technical\MetaNoindexRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectChainRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RedirectLoopRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsDisallowRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsNoindexConflictRule;
use SitePortal\SeoAnalyzer\Rules\Technical\RobotsUnavailableRule;
use SitePortal\SeoAnalyzer\Rules\Technical\SitemapMissingRule;
use SitePortal\SeoAnalyzer\Rules\Technical\SitemapStatusRule;
use SitePortal\SeoAnalyzer\Rules\Technical\Soft404SuspectRule;
use SitePortal\SeoAnalyzer\Rules\Technical\XRobotsNoindexRule;

final class TechnicalRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with all 19 V1.0 technical SEO rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new HttpStatusRule(),
            new RedirectChainRule(),
            new RedirectLoopRule(),
            new HttpsRule(),
            new HttpsDowngradeRule(),
            new RobotsDisallowRule(),
            new RobotsUnavailableRule(),
            new MetaNoindexRule(),
            new XRobotsNoindexRule(),
            new RobotsNoindexConflictRule(),
            new CanonicalMissingRule(),
            new CanonicalEmptyRule(),
            new CanonicalMultiConflictRule(),
            new CanonicalRedirectRule(),
            new CanonicalOutOfScopeRule(),
            new Soft404SuspectRule(),
            new IndexabilityInferenceRule(),
            new SitemapMissingRule(),
            new SitemapStatusRule(),
        ]);
    }
}

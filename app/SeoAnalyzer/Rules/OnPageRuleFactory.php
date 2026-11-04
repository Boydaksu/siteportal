<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\OnPage\AnchorEmptyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\CharsetRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\H1EmptyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\HeadingHierarchyRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\HtmlLangRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\ImageAltRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\MetaDescriptionMissingRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\MetaKeywordsRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\OgMetadataRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\RelNofollowRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleGenericRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleMissingRule;
use SitePortal\SeoAnalyzer\Rules\OnPage\TitleTruncationRiskRule;

final class OnPageRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with all 13 V1.0 On-Page SEO rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new TitleMissingRule(),
            new TitleGenericRule(),
            new TitleTruncationRiskRule(),
            new MetaDescriptionMissingRule(),
            new H1EmptyRule(),
            new HeadingHierarchyRule(),
            new HtmlLangRule(),
            new CharsetRule(),
            new OgMetadataRule(),
            new MetaKeywordsRule(),
            new AnchorEmptyRule(),
            new RelNofollowRule(),
            new ImageAltRule(),
        ]);
    }
}

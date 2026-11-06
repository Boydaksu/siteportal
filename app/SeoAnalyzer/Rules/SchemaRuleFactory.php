<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Rules;

use SitePortal\SeoAnalyzer\Domain\RuleRegistry;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaInvalidContextRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaInvalidJsonLdRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaNoRichTypeRule;
use SitePortal\SeoAnalyzer\Rules\Schema\SchemaRequiredForTypeRule;

final class SchemaRuleFactory
{
    /**
     * Creates and populates a RuleRegistry with V1.2 Schema & Structured Data rules.
     */
    public static function createRegistry(): RuleRegistryInterface
    {
        return new RuleRegistry([
            new SchemaInvalidJsonLdRule(),
            new SchemaInvalidContextRule(),
            new SchemaRequiredForTypeRule(),
            new SchemaNoRichTypeRule(),
        ]);
    }
}

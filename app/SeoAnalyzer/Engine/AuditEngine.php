<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Engine;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;
use SitePortal\SeoAnalyzer\Domain\RuleRegistryInterface;

final class AuditEngine implements AuditEngineInterface
{
    public function __construct(
        private readonly RuleRegistryInterface $registry
    ) {
    }

    /**
     * Executes all registered rules deterministically and returns the findings list.
     *
     * @return list<Finding>
     */
    public function audit(PageSnapshot $snapshot): array
    {
        $findings = [];
        foreach ($this->registry->all() as $rule) {
            $findings[] = $rule->evaluate($snapshot);
        }

        return $findings;
    }

    public function getRegistry(): RuleRegistryInterface
    {
        return $this->registry;
    }
}

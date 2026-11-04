<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Engine;

use SitePortal\SeoAnalyzer\Domain\Finding;
use SitePortal\SeoAnalyzer\Domain\PageSnapshot;

interface AuditEngineInterface
{
    /**
     * Executes all registered rules against the provided page snapshot
     * and returns the collected findings.
     *
     * @return list<Finding>
     */
    public function audit(PageSnapshot $snapshot): array;
}

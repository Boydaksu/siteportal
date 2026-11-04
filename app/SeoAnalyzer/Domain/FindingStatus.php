<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

enum FindingStatus: string
{
    case PASS = 'PASS';
    case WARN = 'WARN';
    case FAIL = 'FAIL';
    case UNKNOWN = 'UNKNOWN';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
}

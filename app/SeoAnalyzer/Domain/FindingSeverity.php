<?php

declare(strict_types=1);

namespace SitePortal\SeoAnalyzer\Domain;

enum FindingSeverity: string
{
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case INFO = 'info';
}

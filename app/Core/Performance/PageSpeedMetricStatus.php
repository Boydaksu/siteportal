<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Metric rating status based on Google Core Web Vitals thresholds.
 */
enum PageSpeedMetricStatus: string
{
    case GOOD = 'good';
    case NEEDS_IMPROVEMENT = 'needs_improvement';
    case POOR = 'poor';
    case UNKNOWN = 'unknown';
}

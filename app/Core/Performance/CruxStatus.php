<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Status of a Chrome UX Report (CrUX) query.
 */
enum CruxStatus: string
{
    case AVAILABLE = 'available';
    case NO_DATA = 'no_data';
    case UNAVAILABLE = 'unavailable';
}

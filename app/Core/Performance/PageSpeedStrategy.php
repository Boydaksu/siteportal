<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * PageSpeed analysis strategy / form factor.
 */
enum PageSpeedStrategy: string
{
    case MOBILE = 'mobile';
    case DESKTOP = 'desktop';
}

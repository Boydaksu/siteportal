<?php

declare(strict_types=1);

namespace SitePortal\Core\Performance;

/**
 * Form factor filters supported by Chrome UX Report (CrUX) API.
 */
enum CruxFormFactor: string
{
    case ALL_FORM_FACTORS = 'ALL_FORM_FACTORS';
    case PHONE = 'PHONE';
    case DESKTOP = 'DESKTOP';
    case TABLET = 'TABLET';

    public static function fromStrategy(PageSpeedStrategy $strategy): self
    {
        return match ($strategy) {
            PageSpeedStrategy::MOBILE => self::PHONE,
            PageSpeedStrategy::DESKTOP => self::DESKTOP,
        };
    }
}

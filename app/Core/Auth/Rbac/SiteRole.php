<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Rbac;

enum SiteRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }
}

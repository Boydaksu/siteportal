<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Rbac;

enum PlatformRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}

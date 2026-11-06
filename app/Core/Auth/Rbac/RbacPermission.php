<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Rbac;

enum RbacPermission: string
{
    case VIEW_SITE = 'site.view';
    case AUDIT_SITE = 'site.audit';
    case MANAGE_MEMBERS = 'site.manage_members';
    case DELETE_SITE = 'site.delete';
    case VIEW_ANALYSIS = 'analysis.view';
    case DELETE_ANALYSIS = 'analysis.delete';
    case MANAGE_PLATFORM = 'platform.manage';
}

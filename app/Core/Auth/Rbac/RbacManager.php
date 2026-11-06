<?php

declare(strict_types=1);

namespace SitePortal\Core\Auth\Rbac;

use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Auth\Models\User;
use SitePortal\Core\Database\Connection;

final class RbacManager
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Kullanıcının belirtilen yetkiye sahip olup olmadığını kontrol eder.
     */
    public function can(
        User $user,
        RbacPermission $permission,
        ?int $siteId = null,
        ?int $analysisId = null
    ): bool {
        // 1. Platform yöneticisi (admin) tüm yetkilere sahiptir
        if ($user->isAdmin()) {
            return true;
        }

        // Platform yönetimi yetkisi sadece admin içindir
        if ($permission === RbacPermission::MANAGE_PLATFORM) {
            return false;
        }

        // 2. Analiz bazlı yetki kontrolü
        if ($analysisId !== null) {
            return $this->canAccessAnalysis($user, $permission, $analysisId);
        }

        // 3. Site bazlı yetki kontrolü
        if ($siteId !== null) {
            return $this->canAccessSite($user, $permission, $siteId);
        }

        return false;
    }

    /**
     * Yetki yoksa ForbiddenException fırlatır.
     *
     * @throws ForbiddenException
     */
    public function assertCan(
        User $user,
        RbacPermission $permission,
        ?int $siteId = null,
        ?int $analysisId = null
    ): void {
        if (!$this->can($user, $permission, $siteId, $analysisId)) {
            throw new ForbiddenException("auth.forbidden_action: {$permission->value}");
        }
    }

    /**
     * Kullanıcının bir sitedeki rolünü sorgular.
     */
    public function getSiteRole(int $userId, int $siteId): ?SiteRole
    {
        // 1. Doğrudan sites tablosundaki sahip kontrolü
        $site = $this->db->fetch(
            'SELECT owner_user_id FROM sites WHERE id = :id',
            ['id' => $siteId]
        );

        if ($site !== null && (int) ($site['owner_user_id'] ?? 0) === $userId) {
            return SiteRole::OWNER;
        }

        // 2. site_members tablosundaki üyelik kontrolü
        $member = $this->db->fetch(
            'SELECT role FROM site_members WHERE site_id = :site_id AND user_id = :user_id',
            ['site_id' => $siteId, 'user_id' => $userId]
        );

        if ($member === null) {
            return null;
        }

        return SiteRole::tryFrom((string) $member['role']);
    }

    /**
     * Siteye üye atar veya mevcut rolü günceller.
     */
    public function assignMember(int $siteId, int $userId, SiteRole $role): void
    {
        $existing = $this->db->fetch(
            'SELECT id FROM site_members WHERE site_id = :site_id AND user_id = :user_id',
            ['site_id' => $siteId, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE site_members SET role = :role WHERE id = :id',
                ['role' => $role->value, 'id' => $existing['id']]
            );
        } else {
            $this->db->execute(
                'INSERT INTO site_members (site_id, user_id, role) VALUES (:site_id, :user_id, :role)',
                ['site_id' => $siteId, 'user_id' => $userId, 'role' => $role->value]
            );
        }
    }

    /**
     * Siteden üyeyi çıkarır.
     */
    public function removeMember(int $siteId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM site_members WHERE site_id = :site_id AND user_id = :user_id',
            ['site_id' => $siteId, 'user_id' => $userId]
        );
    }

    private function canAccessSite(User $user, RbacPermission $permission, int $siteId): bool
    {
        $role = $this->getSiteRole($user->getId(), $siteId);
        if ($role === null) {
            return false;
        }

        return match ($permission) {
            RbacPermission::VIEW_SITE,
            RbacPermission::AUDIT_SITE => true, // Hem owner hem member siteyi görebilir ve analiz başlatabilir

            RbacPermission::MANAGE_MEMBERS,
            RbacPermission::DELETE_SITE => $role->isOwner(), // Sadece owner üye yönetebilir veya siteyi silebilir

            default => false,
        };
    }

    private function canAccessAnalysis(User $user, RbacPermission $permission, int $analysisId): bool
    {
        $analysis = $this->db->fetch(
            'SELECT site_id, user_id FROM analyses WHERE id = :id',
            ['id' => $analysisId]
        );

        if ($analysis === null) {
            return false;
        }

        $analysisSiteId = isset($analysis['site_id']) ? (int) $analysis['site_id'] : null;
        $analysisUserId = isset($analysis['user_id']) ? (int) $analysis['user_id'] : null;

        // Eğer analiz bir siteye bağlıysa, kullanıcının o sitedeki rolüne bakılır
        if ($analysisSiteId !== null && $analysisSiteId > 0) {
            $siteRole = $this->getSiteRole($user->getId(), $analysisSiteId);
            if ($siteRole === null) {
                return false;
            }

            return match ($permission) {
                RbacPermission::VIEW_ANALYSIS => true,
                RbacPermission::DELETE_ANALYSIS => $siteRole->isOwner(),
                default => false,
            };
        }

        // Eğer analiz bağımsız bir tekil sayfa analizi ise
        if ($analysisUserId === $user->getId()) {
            return match ($permission) {
                RbacPermission::VIEW_ANALYSIS,
                RbacPermission::DELETE_ANALYSIS => true,
                default => false,
            };
        }

        return false;
    }
}

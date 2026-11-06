<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Exceptions\ForbiddenException;
use SitePortal\Core\Auth\Models\User;
use SitePortal\Core\Auth\Rbac\PlatformRole;
use SitePortal\Core\Auth\Rbac\RbacManager;
use SitePortal\Core\Auth\Rbac\RbacPermission;
use SitePortal\Core\Auth\Rbac\SiteRole;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class RbacManagerTest extends TestCase
{
    private Connection $db;
    private RbacManager $rbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        // 1. Admin kullanıcı (id 1)
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (1, 'admin@example.com', 'h', 'admin', 'tr-TR', 'active')"
        );

        // 2. Owner kullanıcı (id 2)
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (2, 'owner@example.com', 'h', 'user', 'tr-TR', 'active')"
        );

        // 3. Member kullanıcı (id 3)
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (3, 'member@example.com', 'h', 'user', 'tr-TR', 'active')"
        );

        // 4. Yetkisiz başka kullanıcı (id 4)
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (4, 'stranger@example.com', 'h', 'user', 'tr-TR', 'active')"
        );

        // Site: id 10, owner: user 2
        $this->db->execute(
            "INSERT INTO sites (id, normalized_origin, owner_user_id) "
            . "VALUES (10, 'https://mysite.com', 2)"
        );

        // Site üyesi: user 3 member rolünde
        $this->db->execute(
            "INSERT INTO site_members (site_id, user_id, role) "
            . "VALUES (10, 3, 'member')"
        );

        // Analiz 100: site 10'a ait
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (100, 'pub-100', 10, 2, 'crawl', 'completed')"
        );

        // Analiz 200: site_id yok, bağımsız tekil sayfa analizi (user 2'ye ait)
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (200, 'pub-200', NULL, 2, 'single_page', 'completed')"
        );

        $this->rbac = new RbacManager($this->db);
    }

    public function testAdminHasAllPermissions(): void
    {
        $admin = User::fromArray(['id' => 1, 'email' => 'admin@example.com', 'role' => 'admin']);

        self::assertTrue($this->rbac->can($admin, RbacPermission::MANAGE_PLATFORM));
        self::assertTrue($this->rbac->can($admin, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($admin, RbacPermission::DELETE_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($admin, RbacPermission::MANAGE_MEMBERS, siteId: 10));
        self::assertTrue($this->rbac->can($admin, RbacPermission::VIEW_ANALYSIS, analysisId: 100));
        self::assertTrue($this->rbac->can($admin, RbacPermission::DELETE_ANALYSIS, analysisId: 100));
    }

    public function testSiteOwnerPermissions(): void
    {
        $owner = User::fromArray(['id' => 2, 'email' => 'owner@example.com', 'role' => 'user']);

        self::assertFalse($this->rbac->can($owner, RbacPermission::MANAGE_PLATFORM));
        self::assertTrue($this->rbac->can($owner, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($owner, RbacPermission::AUDIT_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($owner, RbacPermission::MANAGE_MEMBERS, siteId: 10));
        self::assertTrue($this->rbac->can($owner, RbacPermission::DELETE_SITE, siteId: 10));

        self::assertTrue($this->rbac->can($owner, RbacPermission::VIEW_ANALYSIS, analysisId: 100));
        self::assertTrue($this->rbac->can($owner, RbacPermission::DELETE_ANALYSIS, analysisId: 100));
        self::assertTrue($this->rbac->can($owner, RbacPermission::VIEW_ANALYSIS, analysisId: 200));
    }

    public function testSiteMemberPermissions(): void
    {
        $member = User::fromArray(['id' => 3, 'email' => 'member@example.com', 'role' => 'user']);

        // Member görüntüleyebilir ve analiz yapabilir
        self::assertTrue($this->rbac->can($member, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($member, RbacPermission::AUDIT_SITE, siteId: 10));
        self::assertTrue($this->rbac->can($member, RbacPermission::VIEW_ANALYSIS, analysisId: 100));

        // Member silemez veya üye yönetemez
        self::assertFalse($this->rbac->can($member, RbacPermission::MANAGE_MEMBERS, siteId: 10));
        self::assertFalse($this->rbac->can($member, RbacPermission::DELETE_SITE, siteId: 10));
        self::assertFalse($this->rbac->can($member, RbacPermission::DELETE_ANALYSIS, analysisId: 100));
        self::assertFalse($this->rbac->can($member, RbacPermission::VIEW_ANALYSIS, analysisId: 200));
    }

    public function testUnauthorizedUserIsForbidden(): void
    {
        $stranger = User::fromArray(['id' => 4, 'email' => 'stranger@example.com', 'role' => 'user']);

        self::assertFalse($this->rbac->can($stranger, RbacPermission::VIEW_SITE, siteId: 10));
        self::assertFalse($this->rbac->can($stranger, RbacPermission::VIEW_ANALYSIS, analysisId: 100));
        self::assertFalse($this->rbac->can($stranger, RbacPermission::VIEW_ANALYSIS, analysisId: 200));

        $this->expectException(ForbiddenException::class);
        $this->rbac->assertCan($stranger, RbacPermission::VIEW_SITE, siteId: 10);
    }

    public function testAssignAndRemoveMember(): void
    {
        self::assertNull($this->rbac->getSiteRole(4, 10));

        $this->rbac->assignMember(10, 4, SiteRole::MEMBER);
        self::assertSame(SiteRole::MEMBER, $this->rbac->getSiteRole(4, 10));

        $this->rbac->assignMember(10, 4, SiteRole::OWNER);
        self::assertSame(SiteRole::OWNER, $this->rbac->getSiteRole(4, 10));

        $this->rbac->removeMember(10, 4);
        self::assertNull($this->rbac->getSiteRole(4, 10));
    }
}

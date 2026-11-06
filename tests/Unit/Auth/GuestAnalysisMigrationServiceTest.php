<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Services\GuestAnalysisMigrationService;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;

final class GuestAnalysisMigrationServiceTest extends TestCase
{
    private Connection $db;
    private GuestAnalysisMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => ':memory:']);
        (new MigrationRunner($this->db))->migrate();

        // 1. Hedef kullanıcı (id 5)
        $this->db->execute(
            "INSERT INTO users (id, email, password_hash, role, locale, status) "
            . "VALUES (5, 'target@example.com', 'h', 'user', 'tr-TR', 'active')"
        );

        // 2. Sahipsiz misafir analizi (id 101, user_id NULL)
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (101, 'guest-101', NULL, NULL, 'single_page', 'completed')"
        );

        // 3. Sahipsiz misafir analizi (id 102, user_id NULL)
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (102, 'guest-102', NULL, NULL, 'single_page', 'completed')"
        );

        // 4. Başka kullanıcıya ait analiz (id 103, user_id 99)
        $this->db->execute(
            "INSERT INTO analyses (id, public_id, site_id, user_id, mode, status) "
            . "VALUES (103, 'other-103', NULL, 99, 'single_page', 'completed')"
        );

        $this->service = new GuestAnalysisMigrationService($this->db);
    }

    public function testMigrateGuestAnalysesSuccessfully(): void
    {
        $migrated = $this->service->migrate(5, [101, 102, 103]);

        // Yalnızca 101 ve 102 aktarılmalı, 103 başkasına ait olduğu için aktarılmamalı
        self::assertSame(2, $migrated);

        $a101 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 101');
        $a102 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 102');
        $a103 = $this->db->fetch('SELECT user_id FROM analyses WHERE id = 103');

        self::assertNotNull($a101);
        self::assertNotNull($a102);
        self::assertNotNull($a103);

        self::assertSame(5, (int) $a101['user_id']);
        self::assertSame(5, (int) $a102['user_id']);
        self::assertSame(99, (int) $a103['user_id']); // Başkasının analizi korunur
    }

    public function testMigrateEmptyOrInvalidDoesNothing(): void
    {
        self::assertSame(0, $this->service->migrate(5, []));
        self::assertSame(0, $this->service->migrate(0, [101]));
        self::assertSame(0, $this->service->migrate(-1, [101]));
    }
}

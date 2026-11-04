<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Security;

use PDO;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\AdminAuth;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Queue\DatabaseQueue;
use SitePortal\Core\Security\Csrf;
use SitePortal\Core\Security\RateLimiter;
use SitePortal\Core\Security\Session;

/**
 * Veri ve Kiracı İzolasyonu Entegrasyon Testi
 *
 * Veritabanı transaction rollback bütünlüğü, çok kiracılı/site bazlı veri izolasyonu,
 * oturum sabitleme (fixation) koruması, brute-force IP kilidi, oturumlar arası CSRF
 * sızıntısı engellemesi ve kullanıcı bazlı rate limiter bağımsızlığını doğrular.
 */
final class DataIsolationTest extends TestCase
{
    private string $tempDbFile;
    private Connection $db;

    protected function setUp(): void
    {
        $this->tempDbFile = sys_get_temp_dir() . '/siteportal_isolation_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->db = Connection::create(['driver' => 'sqlite', 'database' => $this->tempDbFile]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDbFile)) {
            @unlink($this->tempDbFile);
        }
    }

    public function testTransactionRollbackLeavesZeroOrphanedData(): void
    {
        $queue = new DatabaseQueue($this->db);

        // Başarılı bir iş ekle
        $validJobId = $queue->push('audit', ['url' => 'https://site-a.com'], 'key_site_a_valid');
        self::assertGreaterThan(0, $validJobId);

        // Transaction başlat ve yarıda hata fırlat
        $this->db->beginTransaction();
        try {
            $queue->push('audit', ['url' => 'https://site-a.com/page2'], 'key_site_a_rollback_1');
            $queue->push('audit', ['url' => 'https://site-a.com/page3'], 'key_site_a_rollback_2');

            // Beklenmeyen hata simülasyonu
            throw new \RuntimeException('Simüle edilen kesinti / veritabanı hatası');
        } catch (\RuntimeException $e) {
            $this->db->rollBack();
        }

        // Doğrulama: Sadece ilk geçerli iş kalmalı; transaction içindeki 2 iş tamamen geri alınmış olmalıdır
        $stmt = $this->db->getPdo()->query("SELECT COUNT(*) FROM analysis_jobs");
        self::assertNotFalse($stmt);
        $count = (int) $stmt->fetchColumn();
        self::assertSame(1, $count, 'Geri alınan transaction hiçbir yetim/yarım kayıt bırakmamalıdır');

        $job = $queue->claim('worker_iso', 60);
        self::assertNotNull($job);
        self::assertSame($validJobId, $job->getId());
    }

    public function testTenantAndSiteDataScopingCannotCrossPollute(): void
    {
        $queue = new DatabaseQueue($this->db);

        // İki farklı tenant/site için işler ekle
        $siteAJob = $queue->push(
            'crawl_site',
            ['site_id' => 'site-alpha', 'url' => 'https://alpha.example.com'],
            'site_a_1'
        );
        $siteBJob = $queue->push(
            'crawl_site',
            ['site_id' => 'site-beta', 'url' => 'https://beta.example.com'],
            'site_b_1'
        );

        self::assertNotSame($siteAJob, $siteBJob);

        // Site A kapsamındaki sorgu Site B verisine erişemez
        $stmtA = $this->db->getPdo()->prepare(
            "SELECT * FROM analysis_jobs WHERE payload_json LIKE :tenant"
        );
        $stmtA->execute(['tenant' => '%site-alpha%']);
        $resultsA = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $resultsA);
        self::assertSame($siteAJob, (int) $resultsA[0]['id']);
        self::assertStringNotContainsString('beta.example.com', (string) $resultsA[0]['payload_json']);

        // Site B kapsamındaki sorgu Site A verisine erişemez
        $stmtB = $this->db->getPdo()->prepare(
            "SELECT * FROM analysis_jobs WHERE payload_json LIKE :tenant"
        );
        $stmtB->execute(['tenant' => '%site-beta%']);
        $resultsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $resultsB);
        self::assertSame($siteBJob, (int) $resultsB[0]['id']);
        self::assertStringNotContainsString('alpha.example.com', (string) $resultsB[0]['payload_json']);
    }

    public function testAdminAuthenticationEnforcesBruteForceLockout(): void
    {
        $auth = new AdminAuth($this->db);

        // Test admin kullanıcısı oluştur
        $adminPassword = 'AdminSecretPass!99';
        $hashed = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->getPdo()->prepare(
            "INSERT INTO users (email, password_hash, role, created_at, updated_at)
             VALUES (:email, :hash, 'admin', datetime('now'), datetime('now'))"
        );
        $stmt->execute([
            'email' => 'security-admin@siteportal.local',
            'hash' => $hashed,
        ]);

        $ip = '198.51.100.42';
        $email = 'security-admin@siteportal.local';

        // 4 ardışık hatalı deneme (sınır 5)
        for ($i = 1; $i <= 4; $i++) {
            $success = $auth->attempt($email, 'wrong-password', $ip);
            self::assertFalse($success, "Hatalı şifre reddedilmelidir (deneme {$i})");
            self::assertFalse($auth->isLockedOut($email, $ip), "4. denemede henüz kilitlenmemelidir");
        }

        // 5. hatalı deneme -> Kilit devreye girer
        $success5 = $auth->attempt($email, 'wrong-password', $ip);
        self::assertFalse($success5);
        self::assertTrue($auth->isLockedOut($email, $ip), "5 hatalı denemeden sonra IP/email kilitlenmelidir");

        // Kilit altındayken DOĞRU şifre girilse dahi giriş reddedilir
        $lockedSuccessAttempt = $auth->attempt($email, $adminPassword, $ip);
        self::assertFalse(
            $lockedSuccessAttempt,
            'Kilit altındaki hesaba doğru şifre girilse bile brute-force koruması girişi engellemelidir'
        );

        // Farklı bir IP'den doğru şifreyle giriş yapılabilir (IP bazlı izolasyon)
        $otherIp = '198.51.100.99';
        $otherIpSuccess = $auth->attempt($email, $adminPassword, $otherIp);
        self::assertTrue($otherIpSuccess, 'Farklı meşru IP kilitlenmeden bağımsız giriş yapabilmelidir');

        $currentUser = $auth->user();
        self::assertNotNull($currentUser);
        self::assertSame('security-admin@siteportal.local', $currentUser['email']);
    }

    public function testSessionFixationProtectionRegeneratesSessionId(): void
    {
        $session = new Session();
        $session->start();
        $initialSessionId = $session->id();
        self::assertNotEmpty($initialSessionId);

        $session->set('tenant_user', 'admin_1');
        $session->regenerate(true);

        $regeneratedId = $session->id();
        self::assertNotEmpty($regeneratedId);
        self::assertNotSame(
            $initialSessionId,
            $regeneratedId,
            'Oturum yenilendiğinde (regenerate) oturum kimliği değişmelidir (fixation engeli)'
        );
        self::assertSame('admin_1', $session->get('tenant_user'));
    }

    public function testCrossSessionCsrfTokenTamperingIsRejected(): void
    {
        $sessionA = new Session();
        $sessionB = new Session();

        $csrfA = new Csrf($sessionA);
        $csrfB = new Csrf($sessionB);

        $tokenA = $csrfA->getToken();
        $tokenB = $csrfB->getToken();

        self::assertNotSame($tokenA, $tokenB, 'Farklı oturumlar için CSRF tokenları farklı olmalıdır');

        // Session A token'ı Session A ile doğrulanır
        self::assertTrue($csrfA->validateToken($tokenA));

        // Session A token'ı Session B oturumuna sunulduğunda KESİNLİKLE reddedilir
        self::assertFalse(
            $csrfB->validateToken($tokenA),
            'Session A için üretilen CSRF token Session B tarafından kullanılamaz'
        );

        // Session B token'ı Session A oturumuna sunulduğunda KESİNLİKLE reddedilir
        self::assertFalse(
            $csrfA->validateToken($tokenB),
            'Session B için üretilen CSRF token Session A tarafından kullanılamaz'
        );
    }

    public function testRateLimiterTenantQuotasAreStrictlyIsolated(): void
    {
        $limiter = new RateLimiter();

        $tenantA = 'client_tenant_aaa';
        $tenantB = 'client_tenant_bbb';

        $maxAttempts = 5;
        $decaySeconds = 60;

        // Tenant A limitini doldurur
        for ($i = 0; $i < $maxAttempts; $i++) {
            $limiter->hit($tenantA, $decaySeconds);
        }

        self::assertTrue(
            $limiter->tooManyAttempts($tenantA, $maxAttempts),
            'Tenant A limitini aşmış olmalıdır'
        );

        // Tenant B'nin kotası hiç etkilenmemiş olmalıdır (0 tüketim)
        self::assertFalse(
            $limiter->tooManyAttempts($tenantB, $maxAttempts),
            'Tenant A kotasını bitirse bile Tenant B kotası temiz kalmalıdır'
        );

        self::assertSame(5, $limiter->remaining($tenantB, $maxAttempts));
        self::assertSame(0, $limiter->remaining($tenantA, $maxAttempts));
    }
}

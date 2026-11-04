<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Analysis\AnalysisLifecycleService;
use SitePortal\Core\Analysis\AnalysisSharingService;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;

final class AnalysisSharingServiceTest extends TestCase
{
    private Connection $db;
    private MigrationRunner $runner;
    private Config $config;
    private AnalysisLifecycleService $lifecycleService;
    private AnalysisSharingService $sharingService;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->runner = new MigrationRunner($this->db);
        $this->runner->migrate();

        $this->config = Config::getInstance();
        $dnsResolver = new MockDnsResolver(['example.com' => ['93.184.216.34']]);
        $urlValidator = new UrlValidator(new UrlSecurityPolicy(allowPrivateIps: false), dnsResolver: $dnsResolver);

        $this->lifecycleService = new AnalysisLifecycleService(
            $this->db,
            urlValidator: $urlValidator,
            config: $this->config
        );
        $this->sharingService = new AnalysisSharingService($this->db, $this->config);
    }

    public function testGeneratesUnpredictableShareToken(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        $result = $this->sharingService->generateShareToken($publicId, 48);

        self::assertNotEmpty($result['share_token']);
        self::assertSame(64, strlen($result['share_token'])); // 32 bytes hex = 64 chars
        self::assertStringContainsString("token={$result['share_token']}", $result['share_url']);

        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);
        self::assertSame($result['share_token'], $analysis['share_token']);
        self::assertSame(0, (int) $analysis['is_revoked']);
    }

    public function testRevokesShareTokenImmediately(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        $this->sharingService->generateShareToken($publicId, 24);
        $revoked = $this->sharingService->revokeShareToken($publicId);

        self::assertTrue($revoked);

        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);
        self::assertNull($analysis['share_token']);
        self::assertSame(1, (int) $analysis['is_revoked']);
    }

    public function testCanAccessAllowsPublicAndUnlistedForAnonymous(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        // Varsayılan unlisted anonim analiz için serbest
        self::assertTrue($this->sharingService->canAccess($analysis));

        // Public olarak ayarlandığında da serbest
        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PUBLIC);
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);
        self::assertTrue($this->sharingService->canAccess($analysis));
    }

    public function testCanAccessBlocksPrivateReportWithoutValidToken(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        // Raporu private yap
        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        // Tokensiz erişim engellenmeli (T04 IDOR kalkanı)
        self::assertFalse($this->sharingService->canAccess($analysis));
        self::assertFalse($this->sharingService->canAccess($analysis, null, 'invalid_token_123'));
    }

    public function testCanAccessAllowsPrivateReportWithValidToken(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);
        $share = $this->sharingService->generateShareToken($publicId, 24);

        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        // Doğru token ile erişilebilmeli
        self::assertTrue($this->sharingService->canAccess($analysis, null, $share['share_token']));

        // İptal edildikten sonra erişilememeli
        $this->sharingService->revokeShareToken($publicId);
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);
        self::assertFalse($this->sharingService->canAccess($analysis, null, $share['share_token']));
    }

    public function testCanAccessBlocksExpiredShareToken(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE);
        $share = $this->sharingService->generateShareToken($publicId, 24);

        // Süreyi geçmiş bir tarihe alalım
        $pastDate = date('Y-m-d H:i:s', time() - 3600);
        $this->db->execute(
            'UPDATE analyses SET share_token_expires_at = :past WHERE public_id = :id',
            ['past' => $pastDate, 'id' => $publicId]
        );

        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        // Süresi dolmuş token ile erişilememeli
        self::assertFalse($this->sharingService->canAccess($analysis, null, $share['share_token']));
    }

    public function testCanAccessBlocksExpiredLifecycleAnalysis(): void
    {
        $created = $this->lifecycleService->startAnalysis('https://example.com');
        $publicId = (string) $created['public_id'];

        // Analizin genel saklama süresini (TTL) geçmişe al
        $pastDate = date('Y-m-d H:i:s', time() - 86400);
        $this->db->execute(
            'UPDATE analyses SET expires_at = :past WHERE public_id = :id',
            ['past' => $pastDate, 'id' => $publicId]
        );

        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        self::assertTrue($this->sharingService->isExpired($analysis));
        self::assertFalse($this->sharingService->canAccess($analysis));
    }

    public function testCanAccessAllowsOwnerWithoutToken(): void
    {
        $ownerId = 42;
        $created = $this->lifecycleService->startAnalysis('https://example.com', userId: $ownerId);
        $publicId = (string) $created['public_id'];

        $this->sharingService->setVisibility($publicId, AnalysisSharingService::VISIBILITY_PRIVATE, $ownerId);
        $analysis = $this->lifecycleService->getAnalysis($publicId);
        self::assertNotNull($analysis);

        // Sahibi oturum açtığında tokensiz görebilmeli
        self::assertTrue($this->sharingService->canAccess($analysis, $ownerId));

        // Başka bir kullanıcı veya anonim görememeli
        self::assertFalse($this->sharingService->canAccess($analysis, 999));
        self::assertFalse($this->sharingService->canAccess($analysis, null));
    }
}

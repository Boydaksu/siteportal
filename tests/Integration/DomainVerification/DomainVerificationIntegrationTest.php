<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\DomainVerification;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Auth\Rbac\SiteRole;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\DomainVerification\Dns\DnsResolverInterface;
use SitePortal\Core\DomainVerification\Dns\SystemDnsResolver;
use SitePortal\Core\DomainVerification\Exceptions\InvalidVerificationTokenException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationPermissionException;
use SitePortal\Core\DomainVerification\Services\DomainVerificationService;
use SitePortal\Core\DomainVerification\VerificationMethod;
use SitePortal\Core\DomainVerification\VerificationStatus;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;

final class DomainVerificationIntegrationTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $now = date('Y-m-d H:i:s');

        // Seed users: Alice (1), Bob (2)
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (1, 'alice@example.com', 'pwd_alice', 'user', :now, :now)",
            ['now' => $now]
        );
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (2, 'bob@example.com', 'pwd_bob', 'user', :now, :now)",
            ['now' => $now]
        );

        // Seed site
        $this->db->execute(
            'INSERT INTO sites (id, normalized_origin, created_at) '
            . "VALUES (1, 'https://myshop.com', :now)",
            ['now' => $now]
        );
    }

    public function testEndToEndDnsTxtVerificationAndOwnershipAssignment(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);

        // 1. Alice creates verification challenge
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);
        self::assertSame('pending', $challenge->status->value);

        // 2. DNS TXT record is published
        $dnsMock->expects(self::once())
            ->method('getTxtRecords')
            ->with('myshop.com')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        // 3. Alice verifies domain
        $result = $service->verify($challenge->id, 1);
        self::assertTrue($result->success);

        // 4. Check sites table
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNotNull($site['verified_at']);
        self::assertSame(1, (int) $site['owner_user_id']);
        self::assertSame('dns_txt', $site['verification_method']);

        // 5. Check site_members table: Alice must be owner
        $member = $this->db->fetch('SELECT * FROM site_members WHERE site_id = 1 AND user_id = 1');
        self::assertNotNull($member);
        self::assertSame(SiteRole::OWNER->value, $member['role']);

        // 6. Check domain_verifications record
        $verif = $this->db->fetch('SELECT * FROM domain_verifications WHERE id = :id', ['id' => $challenge->id]);
        self::assertNotNull($verif);
        self::assertSame(VerificationStatus::VERIFIED->value, $verif['status']);
        self::assertNotNull($verif['verified_at']);
        self::assertNull($verif['failure_reason']);
    }

    public function testCrossTenantHijackingBlockedWhenSiteAlreadyVerified(): void
    {
        $dnsStub = $this->createStub(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsStub);

        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsStub->method('getTxtRecords')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        $service->verify($challenge->id, 1);

        // Bob tries to generate challenge for Alice's verified domain
        $this->expectException(VerificationPermissionException::class);
        $this->expectExceptionMessage('Bu domain başka bir kullanıcı tarafından doğrulanmış ve sahiplenilmiştir.');
        $service->generateChallenge(1, 2, VerificationMethod::DNS_TXT);
    }

    public function testCrossTenantVerificationExecutionBlocked(): void
    {
        $dnsStub = $this->createStub(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsStub);

        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        // Bob attempts to execute verify on Alice's challenge ID
        $this->expectException(VerificationPermissionException::class);
        $this->expectExceptionMessage('Bu doğrulama isteğini yalnızca başlatan kullanıcı tamamlayabilir.');
        $service->verify($challenge->id, 2);
    }

    public function testReplayAttackRejectedAfterVerification(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);

        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsMock->expects(self::once())
            ->method('getTxtRecords')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        $service->verify($challenge->id, 1);

        // Second verify call must fail with replay guard exception
        $this->expectException(InvalidVerificationTokenException::class);
        $this->expectExceptionMessage('Bu doğrulama isteği zaten doğrulanmış ve tamamlanmıştır.');
        $service->verify($challenge->id, 1);
    }

    public function testEndToEndHttpFileVerificationFlow(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO sites (id, normalized_origin, created_at) '
            . "VALUES (2, 'https://store.example.com', :now)",
            ['now' => $now]
        );

        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);

        $challenge = $service->generateChallenge(2, 1, VerificationMethod::HTTP_FILE);

        $httpMock->expects(self::once())
            ->method('get')
            ->with('https://store.example.com/.well-known/siteportal-verify.txt')
            ->willReturn(new HttpResponse(
                statusCode: 200,
                headers: ['content-type' => 'text/plain'],
                body: "siteportal-site-verification={$challenge->token}",
                effectiveUrl: 'https://store.example.com/.well-known/siteportal-verify.txt'
            ));

        $result = $service->verify($challenge->id, 1);
        self::assertTrue($result->success);

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 2');
        self::assertNotNull($site);
        self::assertNotNull($site['verified_at']);
        self::assertSame('http_file', $site['verification_method']);
    }

    public function testPeriodicRevalidationRevokesOwnershipWhenProofIsDeleted(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);

        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsMock->expects(self::atLeastOnce())
            ->method('getTxtRecords')
            ->willReturnOnConsecutiveCalls(
                ['siteportal-site-verification=' . $challenge->token],
                [], // recheck apex: not found
                []  // recheck subdomain: not found
            );

        $service->verify($challenge->id, 1);

        // Verification successful initially
        $siteBefore = $this->db->fetch('SELECT verified_at FROM sites WHERE id = 1');
        self::assertNotNull($siteBefore);
        self::assertNotNull($siteBefore['verified_at']);

        // Periodic worker runs recheck
        $passed = $service->recheckVerification(1);
        self::assertFalse($passed);

        // Verification is now revoked
        $siteAfter = $this->db->fetch('SELECT verified_at, verification_method FROM sites WHERE id = 1');
        self::assertNotNull($siteAfter);
        self::assertNull($siteAfter['verified_at']);
        self::assertNull($siteAfter['verification_method']);
    }

    public function testSystemDnsResolverGracefullyHandlesEmptyAndInvalidHosts(): void
    {
        $resolver = new SystemDnsResolver();

        self::assertSame([], $resolver->getTxtRecords(''));
        self::assertSame([], $resolver->getTxtRecords('.'));
        // Non-existent domain safely returns empty array without exception
        $records = $resolver->getTxtRecords('this-domain-surely-does-not-exist-siteportal.invalid');
        self::assertSame([], $records);
    }
}

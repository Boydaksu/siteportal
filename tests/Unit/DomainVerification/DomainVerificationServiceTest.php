<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\DomainVerification;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SitePortal\Core\Auth\Rbac\SiteRole;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\DomainVerification\Dns\DnsResolverInterface;
use SitePortal\Core\DomainVerification\Exceptions\DomainMismatchException;
use SitePortal\Core\DomainVerification\Exceptions\DomainVerificationException;
use SitePortal\Core\DomainVerification\Exceptions\InvalidVerificationTokenException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationChallengeNotFoundException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationPermissionException;
use SitePortal\Core\DomainVerification\Exceptions\VerificationTokenExpiredException;
use SitePortal\Core\DomainVerification\Models\DomainVerificationChallenge;
use SitePortal\Core\DomainVerification\Services\DomainVerificationService;
use SitePortal\Core\DomainVerification\VerificationMethod;
use SitePortal\Core\DomainVerification\VerificationStatus;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;

final class DomainVerificationServiceTest extends TestCase
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

        // Seed sample users
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (1, 'owner@example.com', 'hash1', 'user', :now, :now)",
            ['now' => $now]
        );
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (2, 'attacker@example.com', 'hash2', 'user', :now, :now)",
            ['now' => $now]
        );
        $this->db->execute(
            'INSERT INTO users (id, email, password_hash, role, created_at, updated_at) '
            . "VALUES (99, 'admin@example.com', 'hash_admin', 'admin', :now, :now)",
            ['now' => $now]
        );

        // Seed sample site
        $this->db->execute(
            'INSERT INTO sites (id, normalized_origin, created_at) '
            . "VALUES (1, 'https://example.com', :now)",
            ['now' => $now]
        );
    }

    public function testGenerateChallengeCreatesSecureTokenAndStoresPendingRecord(): void
    {
        $service = new DomainVerificationService($this->db);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        self::assertSame(1, $challenge->siteId);
        self::assertSame(1, $challenge->userId);
        self::assertSame(VerificationMethod::DNS_TXT, $challenge->method);
        self::assertSame(VerificationStatus::PENDING, $challenge->status);
        self::assertSame(64, strlen($challenge->token));
        self::assertStringStartsWith(DomainVerificationChallenge::TOKEN_PREFIX, $challenge->getExpectedRecordValue());
        self::assertFalse($challenge->isExpired());
    }

    public function testGenerateChallengeReusesExistingValidPendingChallenge(): void
    {
        $service = new DomainVerificationService($this->db);
        $challenge1 = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);
        $challenge2 = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        self::assertSame($challenge1->id, $challenge2->id);
        self::assertSame($challenge1->token, $challenge2->token);
    }

    public function testGenerateChallengeRejectsIfSiteAlreadyOwnedAndVerifiedByAnotherUser(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE sites SET verified_at = :now, owner_user_id = 1 WHERE id = 1',
            ['now' => $now]
        );

        $service = new DomainVerificationService($this->db);

        $this->expectException(VerificationPermissionException::class);
        $this->expectExceptionMessage('Bu domain başka bir kullanıcı tarafından doğrulanmış ve sahiplenilmiştir.');
        $service->generateChallenge(1, 2, VerificationMethod::DNS_TXT);
    }

    public function testGenerateChallengeAllowsSameOwnerToRegenerateOrCheck(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE sites SET verified_at = :now, owner_user_id = 1 WHERE id = 1',
            ['now' => $now]
        );

        $service = new DomainVerificationService($this->db);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        self::assertSame(1, $challenge->siteId);
        self::assertSame(VerificationMethod::HTTP_FILE, $challenge->method);
    }

    public function testGenerateChallengeThrowsIfSiteNotFound(): void
    {
        $service = new DomainVerificationService($this->db);

        $this->expectException(DomainVerificationException::class);
        $this->expectExceptionMessage('Site bulunamadı.');
        $service->generateChallenge(999, 1, VerificationMethod::DNS_TXT);
    }

    public function testVerifyThrowsIfChallengeNotFound(): void
    {
        $service = new DomainVerificationService($this->db);

        $this->expectException(VerificationChallengeNotFoundException::class);
        $service->verify(999, 1);
    }

    public function testVerifyThrowsIfReplayAttemptedOnAlreadyVerifiedChallenge(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO domain_verifications '
            . '(id, site_id, user_id, method, token, status, expires_at, created_at, updated_at) '
            . "VALUES (10, 1, 1, 'dns_txt', 'tok123', 'verified', :now, :now, :now)",
            ['now' => $now]
        );

        $service = new DomainVerificationService($this->db);

        $this->expectException(InvalidVerificationTokenException::class);
        $this->expectExceptionMessage('Bu doğrulama isteği zaten doğrulanmış ve tamamlanmıştır.');
        $service->verify(10, 1);
    }

    public function testVerifyThrowsIfRevokedChallenge(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO domain_verifications '
            . '(id, site_id, user_id, method, token, status, expires_at, created_at, updated_at) '
            . "VALUES (11, 1, 1, 'dns_txt', 'tok123', 'revoked', :now, :now, :now)",
            ['now' => $now]
        );

        $service = new DomainVerificationService($this->db);

        $this->expectException(InvalidVerificationTokenException::class);
        $this->expectExceptionMessage('Bu doğrulama isteği iptal edilmiştir.');
        $service->verify(11, 1);
    }

    public function testVerifyThrowsIfRequestingUserDoesNotMatchCreator(): void
    {
        $service = new DomainVerificationService($this->db);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $this->expectException(VerificationPermissionException::class);
        $this->expectExceptionMessage('Bu doğrulama isteğini yalnızca başlatan kullanıcı tamamlayabilir.');
        $service->verify($challenge->id, 2);
    }

    public function testVerifyThrowsIfTokenExpired(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600);
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO domain_verifications '
            . '(id, site_id, user_id, method, token, status, expires_at, created_at, updated_at) '
            . "VALUES (12, 1, 1, 'dns_txt', 'tok123', 'pending', :past, :now, :now)",
            ['past' => $past, 'now' => $now]
        );

        $service = new DomainVerificationService($this->db);

        $this->expectException(VerificationTokenExpiredException::class);
        $this->expectExceptionMessage('Doğrulama tokenının süresi dolmuştur.');
        $service->verify(12, 1);
    }

    public function testVerifyDnsTxtSuccess(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsMock->expects(self::once())
            ->method('getTxtRecords')
            ->with('example.com')
            ->willReturn([
                'v=spf1 include:_spf.google.com ~all',
                'siteportal-site-verification=' . $challenge->token,
            ]);

        $result = $service->verify($challenge->id, 1);

        self::assertTrue($result->success);
        self::assertSame('domain_verification.verified_successfully', $result->messageKey);

        // Check site was updated
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNotNull($site['verified_at']);
        self::assertSame(1, (int) $site['owner_user_id']);
        self::assertSame('dns_txt', $site['verification_method']);

        // Check site_members has owner
        $member = $this->db->fetch('SELECT * FROM site_members WHERE site_id = 1 AND user_id = 1');
        self::assertNotNull($member);
        self::assertSame(SiteRole::OWNER->value, $member['role']);
    }

    public function testVerifyDnsTxtSuccessWithSubdomainChallenge(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsMock->expects(self::exactly(2))
            ->method('getTxtRecords')
            ->willReturnCallback(function (string $host) use ($challenge): array {
                if ($host === 'example.com') {
                    return ['v=spf1 ~all'];
                }
                if ($host === '_siteportal-challenge.example.com') {
                    return ['siteportal-site-verification=' . $challenge->token];
                }
                return [];
            });

        $result = $service->verify($challenge->id, 1);

        self::assertTrue($result->success);
    }

    public function testVerifyDnsTxtFailureWhenTokenNotFound(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsMock->expects(self::exactly(2))
            ->method('getTxtRecords')
            ->willReturn([]);

        $result = $service->verify($challenge->id, 1);

        self::assertFalse($result->success);
        self::assertSame('domain_verification.dns_record_not_found', $result->messageKey);

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNull($site['verified_at']);
    }

    public function testVerifyHttpFileSuccess(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        $response = new HttpResponse(
            statusCode: 200,
            headers: ['content-type' => 'text/plain'],
            body: "siteportal-site-verification={$challenge->token}\n",
            effectiveUrl: 'https://example.com/.well-known/siteportal-verify.txt'
        );

        $httpMock->expects(self::once())
            ->method('get')
            ->with('https://example.com/.well-known/siteportal-verify.txt')
            ->willReturn($response);

        $result = $service->verify($challenge->id, 1);

        self::assertTrue($result->success);
        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertSame('http_file', $site['verification_method']);
    }

    public function testVerifyHttpFileSuccessWithPlainTokenOrMultiline(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        $response = new HttpResponse(
            statusCode: 200,
            headers: ['content-type' => 'text/plain'],
            body: "# SitePortal Verification File\n{$challenge->token}\n",
            effectiveUrl: 'https://example.com/.well-known/siteportal-verify.txt'
        );

        $httpMock->expects(self::once())
            ->method('get')
            ->willReturn($response);

        $result = $service->verify($challenge->id, 1);

        self::assertTrue($result->success);
    }

    public function testVerifyHttpFileFailureWhenStatusCodeNot200(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        $response = new HttpResponse(
            statusCode: 404,
            headers: [],
            body: 'Not Found',
            effectiveUrl: 'https://example.com/.well-known/siteportal-verify.txt'
        );

        $httpMock->expects(self::once())
            ->method('get')
            ->willReturn($response);

        $result = $service->verify($challenge->id, 1);

        self::assertFalse($result->success);
        self::assertSame('domain_verification.http_request_failed', $result->messageKey);
    }

    public function testVerifyHttpFileFailureWhenTokenMismatches(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        $response = new HttpResponse(
            statusCode: 200,
            headers: [],
            body: 'siteportal-site-verification=wrong_token_here',
            effectiveUrl: 'https://example.com/.well-known/siteportal-verify.txt'
        );

        $httpMock->expects(self::once())
            ->method('get')
            ->willReturn($response);

        $result = $service->verify($challenge->id, 1);

        self::assertFalse($result->success);
        self::assertSame('domain_verification.http_file_token_mismatch', $result->messageKey);
    }

    public function testVerifyHttpFileFailureOnNetworkOrSsrfException(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = new DomainVerificationService($this->db, $httpMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::HTTP_FILE);

        $httpMock->expects(self::once())
            ->method('get')
            ->willThrowException(new RuntimeException('SSRF blocked: IP is loopback'));

        $result = $service->verify($challenge->id, 1);

        self::assertFalse($result->success);
        self::assertSame('domain_verification.http_request_failed', $result->messageKey);
        self::assertStringContainsString('SSRF blocked', (string) $result->details);
    }

    public function testPeriodicRecheckVerificationRetainsOwnershipWhenTokenPresent(): void
    {
        $dnsStub = $this->createStub(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsStub);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsStub->method('getTxtRecords')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        $service->verify($challenge->id, 1);

        $recheckPassed = $service->recheckVerification(1);
        self::assertTrue($recheckPassed);

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNotNull($site['verified_at']);
    }

    public function testPeriodicRecheckVerificationRevokesOwnershipWhenTokenRemoved(): void
    {
        $dnsMock = $this->createMock(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsMock);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        // Verification phase returns token
        $dnsMock->expects(self::atLeastOnce())
            ->method('getTxtRecords')
            ->willReturnOnConsecutiveCalls(
                ['siteportal-site-verification=' . $challenge->token],
                [], // on recheck: apex returns empty
                []  // on recheck: challenge host returns empty
            );

        $service->verify($challenge->id, 1);

        // Recheck: token removed
        $recheckPassed = $service->recheckVerification(1);
        self::assertFalse($recheckPassed);

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNull($site['verified_at']);
        self::assertNull($site['verification_method']);

        $verif = $this->db->fetch('SELECT * FROM domain_verifications WHERE id = :id', ['id' => $challenge->id]);
        self::assertNotNull($verif);
        self::assertSame(VerificationStatus::REVOKED->value, $verif['status']);
        self::assertStringContainsString('periodic_revalidation_failed', (string) $verif['failure_reason']);
    }

    public function testRevokeVerificationByOwnerOrAdmin(): void
    {
        $dnsStub = $this->createStub(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsStub);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsStub->method('getTxtRecords')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        $service->verify($challenge->id, 1);

        // Revoke by owner
        $service->revokeVerification(1, 1, false);

        $site = $this->db->fetch('SELECT * FROM sites WHERE id = 1');
        self::assertNotNull($site);
        self::assertNull($site['verified_at']);
        self::assertNull($site['verification_method']);
    }

    public function testRevokeVerificationByUnauthorizedUserThrows(): void
    {
        $dnsStub = $this->createStub(DnsResolverInterface::class);
        $service = new DomainVerificationService($this->db, null, $dnsStub);
        $challenge = $service->generateChallenge(1, 1, VerificationMethod::DNS_TXT);

        $dnsStub->method('getTxtRecords')
            ->willReturn(['siteportal-site-verification=' . $challenge->token]);

        $service->verify($challenge->id, 1);

        $this->expectException(VerificationPermissionException::class);
        $service->revokeVerification(1, 2, false); // user 2 is attacker
    }
}

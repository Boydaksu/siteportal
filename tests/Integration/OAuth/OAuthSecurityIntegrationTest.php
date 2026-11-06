<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\OAuth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\OAuth\Exceptions\InvalidStateException;
use SitePortal\Core\OAuth\Exceptions\OAuthTokenExpiredException;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;

final class OAuthSecurityIntegrationTest extends TestCase
{
    private Connection $db;
    private TokenEncryptionService $encryption;
    private PkceService $pkce;

    protected function setUp(): void
    {
        $this->db = Connection::create([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $runner = new MigrationRunner($this->db);
        $runner->migrate();

        $this->encryption = new TokenEncryptionService('integration_test_secret_encryption_key_32_bytes!');
        $this->pkce = new PkceService();

        $now = date('Y-m-d H:i:s');
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

        $this->db->execute(
            'INSERT INTO sites (id, normalized_origin, owner_user_id, verified_at, created_at) '
            . "VALUES (10, 'https://myshop.com', 1, :now, :now)",
            ['now' => $now]
        );
    }

    private function createService(?HttpClientInterface $httpClient = null): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            $this->pkce,
            $httpClient,
            [
                'client_id' => 'gsc_client_id_live',
                'client_secret' => 'gsc_client_secret_live',
                'redirect_uri' => 'https://myshop.com/auth/google/callback',
            ]
        );
    }

    public function testEndToEndOAuthFlowAndEncryptedAtRestVerification(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = $this->createService($httpMock);

        // 1. Initiate OAuth
        $authUrl = $service->createAuthorizationUrl(1, 10);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        // 2. Google redirects with auth code -> Service exchanges code for tokens
        $idPayload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'google_sub_1001',
            'email' => 'alice@google.com',
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $mockIdToken = 'h.' . $idPayload . '.s';

        $tokenPayload = json_encode([
            'access_token' => 'plain_secret_access_token_123',
            'refresh_token' => 'plain_secret_refresh_token_456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'openid email https://www.googleapis.com/auth/webmasters.readonly',
            'id_token' => $mockIdToken,
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with('POST', 'https://oauth2.googleapis.com/token')
            ->willReturn(new HttpResponse(
                200,
                ['content-type' => 'application/json'],
                $tokenPayload,
                'https://oauth2.googleapis.com/token'
            ));

        $connection = $service->handleCallback('auth_code_from_google', $state, 1);

        self::assertSame(1, $connection->userId);
        self::assertSame(10, $connection->siteId);
        self::assertSame('alice@google.com', $connection->providerEmail);
        self::assertTrue($connection->isActive());

        // 3. Directly inspect database to verify at-rest encryption
        $row = $this->db->fetch('SELECT * FROM oauth_connections WHERE id = :id', ['id' => $connection->id]);
        self::assertNotNull($row);

        // Neither plain access token nor plain refresh token must exist in the database!
        self::assertStringNotContainsString('plain_secret_access_token_123', (string) $row['access_token_encrypted']);
        self::assertStringNotContainsString('plain_secret_refresh_token_456', (string) $row['refresh_token_encrypted']);

        // Decryption using service recovers exact original tokens
        $decryptedAccess = $this->encryption->decrypt((string) $row['access_token_encrypted']);
        $decryptedRefresh = $this->encryption->decrypt((string) $row['refresh_token_encrypted']);
        self::assertSame('plain_secret_access_token_123', $decryptedAccess);
        self::assertSame('plain_secret_refresh_token_456', $decryptedRefresh);

        // Valid access token getter returns decrypted value
        self::assertSame('plain_secret_access_token_123', $service->getValidAccessToken($connection->id, 1));
    }

    public function testStateReplayPrevention(): void
    {
        $httpStub = $this->createStub(HttpClientInterface::class);
        $service = $this->createService($httpStub);

        $authUrl = $service->createAuthorizationUrl(1, 10);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        $tokenPayload = json_encode([
            'access_token' => 'tok1',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR);

        $httpStub->method('request')
            ->willReturn(new HttpResponse(200, [], $tokenPayload, 'https://oauth2.googleapis.com/token'));

        $service->handleCallback('code1', $state, 1);

        // Attacker or replay tries same state
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Bu OAuth state parametresi daha önce kullanılmış');
        $service->handleCallback('code2', $state, 1);
    }

    public function testCrossTenantStateHijackingPrevention(): void
    {
        $service = $this->createService();
        $authUrl = $service->createAuthorizationUrl(1, 10); // Alice initiated
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        // Bob tries to consume Alice's state
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('OAuth state parametresi istek sahibi kullanıcı ile eşleşmiyor');
        $service->handleCallback('code', $state, 2);
    }

    public function testRevocationCompletelyCutsOffAccess(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = $this->createService($httpMock);

        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);
        $encAccess = $this->encryption->encrypt('access_token_active');
        $encRefresh = $this->encryption->encrypt('refresh_token_active');

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (1, 1, 10, 'google', :a, :r, '[]', :exp, 'active', :now, :now)",
            [
                'a' => $encAccess,
                'r' => $encRefresh,
                'exp' => $future,
                'now' => $now,
            ]
        );

        // Revoke call triggers Google revoke endpoint
        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/revoke',
                self::callback(function (array $options): bool {
                    $body = (string) ($options['body'] ?? '');
                    return str_contains($body, 'token=refresh_token_active');
                })
            )
            ->willReturn(new HttpResponse(200, [], '', 'https://oauth2.googleapis.com/revoke'));

        $revoked = $service->revokeAccess(1, 1);
        self::assertTrue($revoked);

        // Verify tokens are completely wiped from DB
        $row = $this->db->fetch('SELECT * FROM oauth_connections WHERE id = 1');
        self::assertNotNull($row);
        self::assertSame('revoked', $row['status']);
        self::assertSame('', $row['access_token_encrypted']);
        self::assertSame('', $row['refresh_token_encrypted']);

        // Subsequent access calls MUST fail closed immediately
        $this->expectException(OAuthTokenExpiredException::class);
        $this->expectExceptionMessage('OAuth bağlantı izni kaldırılmıştır (Revoked)');
        $service->getValidAccessToken(1, 1);
    }
}

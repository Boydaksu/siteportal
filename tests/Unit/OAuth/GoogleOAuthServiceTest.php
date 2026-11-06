<?php

declare(strict_types=1);

namespace SitePortal\Tests\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Database\MigrationRunner;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\HttpResponse;
use SitePortal\Core\OAuth\Exceptions\InvalidStateException;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Exceptions\OAuthScopeException;
use SitePortal\Core\OAuth\Exceptions\OAuthTokenExpiredException;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use SitePortal\Core\OAuth\Services\GoogleOAuthService;

final class GoogleOAuthServiceTest extends TestCase
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

        $this->encryption = new TokenEncryptionService('test_encryption_key_for_unit_tests_32_bytes!');
        $this->pkce = new PkceService();
    }

    private function createService(?HttpClientInterface $httpClient = null): GoogleOAuthService
    {
        return new GoogleOAuthService(
            $this->db,
            $this->encryption,
            $this->pkce,
            $httpClient,
            [
                'client_id' => 'mock_client_id_123',
                'client_secret' => 'mock_client_secret_xyz',
                'redirect_uri' => 'https://example.com/auth/google/callback',
            ]
        );
    }

    public function testCreateAuthorizationUrlContainsAllMandatoryParams(): void
    {
        $service = $this->createService();
        $url = $service->createAuthorizationUrl(1, 10);

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        self::assertStringContainsString('client_id=mock_client_id_123', $url);
        self::assertStringContainsString('response_type=code', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString('access_type=offline', $url);
        self::assertStringContainsString('prompt=consent', $url);
        self::assertStringContainsString('webmasters.readonly', $url);

        // Verify state is stored in DB
        $stateRecord = $this->db->fetch('SELECT * FROM oauth_states WHERE user_id = 1 AND site_id = 10');
        self::assertNotNull($stateRecord);
        self::assertSame('google', $stateRecord['provider']);
        self::assertNull($stateRecord['used_at']);
    }

    public function testCreateAuthorizationUrlRejectsDisallowedScopes(): void
    {
        $service = $this->createService();

        $this->expectException(OAuthScopeException::class);
        $this->expectExceptionMessage('İzin verilmeyen OAuth kapsamı talep edildi');
        $service->createAuthorizationUrl(
            1,
            10,
            ['https://www.googleapis.com/auth/webmasters'] // Full write scope rejected!
        );
    }

    public function testHandleCallbackExchangesCodeForTokensAndStoresEncryptedAtRest(): void
    {
        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = $this->createService($httpMock);

        $authUrl = $service->createAuthorizationUrl(1, 10);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        // Sample JWT payload for ID token: {"sub":"google_user_999","email":"user@example.com"}
        $idPayload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'google_user_999',
            'email' => 'user@example.com',
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $mockIdToken = 'header.' . $idPayload . '.signature';

        $tokenResponseBody = json_encode([
            'access_token' => 'ya29.sample_access_token',
            'refresh_token' => '1//sample_refresh_token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'openid email https://www.googleapis.com/auth/webmasters.readonly',
            'id_token' => $mockIdToken,
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/token',
                self::callback(function (array $options): bool {
                    $body = (string) ($options['body'] ?? '');
                    return str_contains($body, 'grant_type=authorization_code')
                        && str_contains($body, 'code_verifier=')
                        && str_contains($body, 'client_id=mock_client_id_123');
                })
            )
            ->willReturn(new HttpResponse(
                200,
                ['content-type' => 'application/json'],
                $tokenResponseBody,
                'https://oauth2.googleapis.com/token'
            ));

        $connection = $service->handleCallback('mock_auth_code', $state, 1);

        self::assertSame(1, $connection->userId);
        self::assertSame(10, $connection->siteId);
        self::assertSame('google', $connection->provider);
        self::assertSame('google_user_999', $connection->providerUserId);
        self::assertSame('user@example.com', $connection->providerEmail);
        self::assertSame('active', $connection->status);

        // Security check: Verify token is NOT stored as plain text in DB
        $dbRow = $this->db->fetch('SELECT * FROM oauth_connections WHERE id = :id', ['id' => $connection->id]);
        self::assertNotNull($dbRow);
        self::assertStringNotContainsString('ya29.sample_access_token', (string) $dbRow['access_token_encrypted']);
        self::assertStringNotContainsString('1//sample_refresh_token', (string) $dbRow['refresh_token_encrypted']);

        // Verify state is marked used
        $stateRecord = $this->db->fetch('SELECT * FROM oauth_states WHERE state = :state', ['state' => $state]);
        self::assertNotNull($stateRecord);
        self::assertNotNull($stateRecord['used_at']);
    }

    public function testHandleCallbackRejectsInvalidOrUnknownState(): void
    {
        $service = $this->createService();

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Geçersiz veya bulunamayan OAuth state parametresi');
        $service->handleCallback('code', 'non_existent_state', 1);
    }

    public function testHandleCallbackRejectsStateBelongingToDifferentUser(): void
    {
        $service = $this->createService();
        $authUrl = $service->createAuthorizationUrl(1, 10);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('OAuth state parametresi istek sahibi kullanıcı ile eşleşmiyor');
        $service->handleCallback('code', $state, 2); // User 2 trying to use User 1's state
    }

    public function testHandleCallbackRejectsReplayOfUsedState(): void
    {
        $httpStub = $this->createStub(HttpClientInterface::class);
        $service = $this->createService($httpStub);

        $authUrl = $service->createAuthorizationUrl(1, 10);
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';

        $tokenResponseBody = json_encode([
            'access_token' => 'token1',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR);

        $httpStub->method('request')
            ->willReturn(new HttpResponse(200, [], $tokenResponseBody, 'https://oauth2.googleapis.com/token'));

        $service->handleCallback('code1', $state, 1);

        // Second call with same state must fail (replay guard)
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('Bu OAuth state parametresi daha önce kullanılmış');
        $service->handleCallback('code2', $state, 1);
    }

    public function testHandleCallbackRejectsExpiredState(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600);
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'INSERT INTO oauth_states '
            . '(state, user_id, site_id, provider, code_verifier, scopes, expires_at, created_at) '
            . "VALUES ('expired_state', 1, 10, 'google', 'verif', '[]', :past, :now)",
            ['past' => $past, 'now' => $now]
        );

        $service = $this->createService();

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('OAuth state parametresinin geçerlilik süresi dolmuştur');
        $service->handleCallback('code', 'expired_state', 1);
    }

    public function testGetValidAccessTokenReturnsDecryptedToken(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);
        $encAccess = $this->encryption->encrypt('my_secret_plain_access_token');
        $encRefresh = $this->encryption->encrypt('my_secret_plain_refresh_token');

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (5, 1, 10, 'google', :a, :r, '[]', :exp, 'active', :now, :now)",
            [
                'a' => $encAccess,
                'r' => $encRefresh,
                'exp' => $future,
                'now' => $now,
            ]
        );

        $service = $this->createService();
        $token = $service->getValidAccessToken(5, 1);

        self::assertSame('my_secret_plain_access_token', $token);
    }

    public function testGetValidAccessTokenAutomaticallyRefreshesWhenExpired(): void
    {
        $now = date('Y-m-d H:i:s');
        $past = date('Y-m-d H:i:s', time() - 100); // Expired
        $encOldAccess = $this->encryption->encrypt('old_expired_access_token');
        $encRefresh = $this->encryption->encrypt('refresh_token_to_use');

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (6, 1, 10, 'google', :a, :r, '[]', :exp, 'active', :now, :now)",
            [
                'a' => $encOldAccess,
                'r' => $encRefresh,
                'exp' => $past,
                'now' => $now,
            ]
        );

        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = $this->createService($httpMock);

        $refreshResponseBody = json_encode([
            'access_token' => 'new_fresh_access_token_from_google',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], JSON_THROW_ON_ERROR);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/token',
                self::callback(function (array $options): bool {
                    $body = (string) ($options['body'] ?? '');
                    return str_contains($body, 'grant_type=refresh_token')
                        && str_contains($body, 'refresh_token=refresh_token_to_use');
                })
            )
            ->willReturn(new HttpResponse(200, [], $refreshResponseBody, 'https://oauth2.googleapis.com/token'));

        $token = $service->getValidAccessToken(6, 1);

        self::assertSame('new_fresh_access_token_from_google', $token);
    }

    public function testRevokeAccessCallsGoogleRevocationEndpointAndClearsLocalTokens(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);
        $encAccess = $this->encryption->encrypt('access_to_revoke');
        $encRefresh = $this->encryption->encrypt('refresh_to_revoke');

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (7, 1, 10, 'google', :a, :r, '[]', :exp, 'active', :now, :now)",
            [
                'a' => $encAccess,
                'r' => $encRefresh,
                'exp' => $future,
                'now' => $now,
            ]
        );

        $httpMock = $this->createMock(HttpClientInterface::class);
        $service = $this->createService($httpMock);

        $httpMock->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/revoke',
                self::callback(function (array $options): bool {
                    $body = (string) ($options['body'] ?? '');
                    return str_contains($body, 'token=refresh_to_revoke');
                })
            )
            ->willReturn(new HttpResponse(200, [], '', 'https://oauth2.googleapis.com/revoke'));

        $revoked = $service->revokeAccess(7, 1);
        self::assertTrue($revoked);

        // Check local DB
        $dbRow = $this->db->fetch('SELECT * FROM oauth_connections WHERE id = 7');
        self::assertNotNull($dbRow);
        self::assertSame('revoked', $dbRow['status']);
        self::assertNotNull($dbRow['revoked_at']);
        self::assertSame('', $dbRow['access_token_encrypted']);
        self::assertSame('', $dbRow['refresh_token_encrypted']);

        // Subsequent getValidAccessToken must fail closed
        $this->expectException(OAuthTokenExpiredException::class);
        $this->expectExceptionMessage('OAuth bağlantı izni kaldırılmıştır (Revoked)');
        $service->getValidAccessToken(7, 1);
    }

    public function testFindConnectionBySite(): void
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + 3600);
        $encAccess = $this->encryption->encrypt('access_token');
        $encRefresh = $this->encryption->encrypt('refresh_token');

        $this->db->execute(
            'INSERT INTO oauth_connections '
            . '(id, user_id, site_id, provider, access_token_encrypted, refresh_token_encrypted, '
            . 'scopes, expires_at, status, created_at, updated_at) '
            . "VALUES (8, 1, 15, 'google', :a, :r, '[\"openid\"]', :exp, 'active', :now, :now)",
            [
                'a' => $encAccess,
                'r' => $encRefresh,
                'exp' => $future,
                'now' => $now,
            ]
        );

        $service = $this->createService();
        $conn = $service->findConnectionBySite(15, 1);

        self::assertNotNull($conn);
        self::assertSame(8, $conn->id);
        self::assertSame(15, $conn->siteId);
        self::assertSame(['openid'], $conn->scopes);

        // Different user cannot access
        self::assertNull($service->findConnectionBySite(15, 2));
    }
}

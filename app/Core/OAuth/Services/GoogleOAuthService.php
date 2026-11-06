<?php

declare(strict_types=1);

namespace SitePortal\Core\OAuth\Services;

use JsonException;
use SitePortal\Core\Config;
use SitePortal\Core\Database\Connection;
use SitePortal\Core\Http\HttpClientInterface;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\OAuth\Exceptions\InvalidStateException;
use SitePortal\Core\OAuth\Exceptions\OAuthException;
use SitePortal\Core\OAuth\Exceptions\OAuthRevocationException;
use SitePortal\Core\OAuth\Exceptions\OAuthScopeException;
use SitePortal\Core\OAuth\Exceptions\OAuthTokenExpiredException;
use SitePortal\Core\OAuth\Models\OAuthConnection;
use SitePortal\Core\OAuth\Models\OAuthTokenResponse;
use SitePortal\Core\OAuth\Security\PkceService;
use SitePortal\Core\OAuth\Security\TokenEncryptionService;
use Throwable;

final class GoogleOAuthService
{
    public const PROVIDER = 'google';

    public const SCOPE_OPENID = 'openid';
    public const SCOPE_EMAIL = 'email';
    public const SCOPE_GSC_READONLY = 'https://www.googleapis.com/auth/webmasters.readonly';

    /** @var list<string> */
    public const ALLOWED_SCOPES = [
        self::SCOPE_OPENID,
        self::SCOPE_EMAIL,
        self::SCOPE_GSC_READONLY,
    ];

    private Connection $db;
    private TokenEncryptionService $encryption;
    private PkceService $pkce;
    private HttpClientInterface $httpClient;

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authEndpoint;
    private string $tokenEndpoint;
    private string $revokeEndpoint;
    private int $stateTtlSeconds;

    /**
     * @param array<string, mixed> $configOverrides
     */
    public function __construct(
        Connection $db,
        ?TokenEncryptionService $encryption = null,
        ?PkceService $pkce = null,
        ?HttpClientInterface $httpClient = null,
        array $configOverrides = []
    ) {
        $this->db = $db;
        $this->encryption = $encryption ?? new TokenEncryptionService();
        $this->pkce = $pkce ?? new PkceService();
        $this->httpClient = $httpClient ?? new SafeHttpClient();

        $config = Config::getInstance();
        $this->clientId = (string) ($configOverrides['client_id']
            ?? $config->get('oauth.client_id', ''));
        $this->clientSecret = (string) ($configOverrides['client_secret']
            ?? $config->get('oauth.client_secret', ''));
        $this->redirectUri = (string) ($configOverrides['redirect_uri']
            ?? $config->get('oauth.redirect_uri', 'http://localhost:8080/auth/google/callback'));

        $this->authEndpoint = (string) ($configOverrides['auth_endpoint']
            ?? $config->get('oauth.endpoints.auth', 'https://accounts.google.com/o/oauth2/v2/auth'));
        $this->tokenEndpoint = (string) ($configOverrides['token_endpoint']
            ?? $config->get('oauth.endpoints.token', 'https://oauth2.googleapis.com/token'));
        $this->revokeEndpoint = (string) ($configOverrides['revoke_endpoint']
            ?? $config->get('oauth.endpoints.revoke', 'https://oauth2.googleapis.com/revoke'));

        $this->stateTtlSeconds = (int) ($configOverrides['state_ttl_seconds']
            ?? $config->get('oauth.state_ttl_seconds', 600));
    }

    /**
     * Generates a Google OAuth authorization URL with PKCE (RFC 7636) and replay-guarded state.
     * Enforces strict scope whitelist (only read-only Search Console and basic identity).
     *
     * @param list<string> $customScopes
     */
    public function createAuthorizationUrl(
        int $userId,
        ?int $siteId = null,
        array $customScopes = []
    ): string {
        $scopes = $customScopes !== [] ? $customScopes : self::ALLOWED_SCOPES;
        $this->validateScopes($scopes);

        $codeVerifier = $this->pkce->generateCodeVerifier(64);
        $codeChallenge = $this->pkce->generateCodeChallenge($codeVerifier);
        $state = $this->pkce->generateState();

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $this->stateTtlSeconds);

        $this->db->execute(
            'INSERT INTO oauth_states '
            . '(state, user_id, site_id, provider, code_verifier, scopes, expires_at, created_at) '
            . 'VALUES (:state, :user_id, :site_id, :provider, :code_verifier, :scopes, :expires_at, :created_at)',
            [
                'state' => $state,
                'user_id' => $userId,
                'site_id' => $siteId,
                'provider' => self::PROVIDER,
                'code_verifier' => $codeVerifier,
                'scopes' => json_encode($scopes, JSON_THROW_ON_ERROR),
                'expires_at' => $expiresAt,
                'created_at' => $now,
            ]
        );

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => PkceService::METHOD_S256,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return $this->authEndpoint . '?' . http_build_query($params);
    }

    /**
     * Handles the OAuth callback.
     * Validates and consumes the state (replay guard), exchanges code for tokens,
     * encrypts tokens at rest, and saves the connection.
     */
    public function handleCallback(
        string $code,
        string $state,
        int $requestingUserId
    ): OAuthConnection {
        $stateRow = $this->db->fetch(
            'SELECT * FROM oauth_states WHERE state = :state AND provider = :provider',
            [
                'state' => $state,
                'provider' => self::PROVIDER,
            ]
        );

        if ($stateRow === null) {
            throw new InvalidStateException('Geçersiz veya bulunamayan OAuth state parametresi.');
        }

        if ((int) $stateRow['user_id'] !== $requestingUserId) {
            throw new InvalidStateException('OAuth state parametresi istek sahibi kullanıcı ile eşleşmiyor.');
        }

        if ($stateRow['used_at'] !== null) {
            throw new InvalidStateException('Bu OAuth state parametresi daha önce kullanılmış (Replay engellendi).');
        }

        $expiresAtTimestamp = strtotime((string) $stateRow['expires_at']);
        if ($expiresAtTimestamp === false || $expiresAtTimestamp < time()) {
            throw new InvalidStateException('OAuth state parametresinin geçerlilik süresi dolmuştur.');
        }

        $now = date('Y-m-d H:i:s');

        // State'i anında kullanılmış olarak işaretle (Replay guard)
        $this->db->execute(
            'UPDATE oauth_states SET used_at = :now WHERE state = :state',
            [
                'now' => $now,
                'state' => $state,
            ]
        );

        // Token takası (Code Exchange with PKCE Verifier)
        $tokenResponse = $this->exchangeCodeForTokens($code, (string) $stateRow['code_verifier']);

        // Kimlik bilgisi ayrıştırma
        $identity = $this->extractIdentityFromIdToken($tokenResponse->idToken);

        // Tokenları AES-256-GCM ile sunucu tarafında şifrele
        $accessTokenEnc = $this->encryption->encrypt($tokenResponse->accessToken);
        $refreshTokenEnc = $this->encryption->encrypt($tokenResponse->refreshToken ?? '');

        $siteId = isset($stateRow['site_id'])
            ? (int) $stateRow['site_id']
            : null;

        $expiresAt = date('Y-m-d H:i:s', time() + $tokenResponse->expiresIn);

        // Mevcut aktif bağlantı var mı kontrol et
        $existing = $this->findActiveConnectionRecord(
            $requestingUserId,
            $siteId
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE oauth_connections SET access_token_encrypted = :access_tok, '
                . 'refresh_token_encrypted = :refresh_tok, token_type = :tok_type, scopes = :scopes, '
                . 'provider_user_id = :p_uid, provider_email = :p_email, expires_at = :exp_at, '
                . 'status = :status, revoked_at = NULL, updated_at = :now WHERE id = :id',
                [
                    'access_tok' => $accessTokenEnc,
                    'refresh_tok' => $refreshTokenEnc !== '' ? $refreshTokenEnc : $existing['refresh_token_encrypted'],
                    'tok_type' => $tokenResponse->tokenType,
                    'scopes' => json_encode($tokenResponse->scopes, JSON_THROW_ON_ERROR),
                    'p_uid' => $identity['sub'],
                    'p_email' => $identity['email'],
                    'exp_at' => $expiresAt,
                    'status' => 'active',
                    'now' => $now,
                    'id' => $existing['id'],
                ]
            );

            $connectionId = (int) $existing['id'];
        } else {
            $this->db->execute(
                'INSERT INTO oauth_connections '
                . '(user_id, site_id, provider, provider_user_id, provider_email, access_token_encrypted, '
                . 'refresh_token_encrypted, token_type, scopes, expires_at, status, created_at, updated_at) '
                . 'VALUES (:uid, :sid, :prov, :p_uid, :p_email, :a_tok, :r_tok, :t_type, :scopes, '
                . ':exp_at, :status, :created_at, :updated_at)',
                [
                    'uid' => $requestingUserId,
                    'sid' => $siteId,
                    'prov' => self::PROVIDER,
                    'p_uid' => $identity['sub'],
                    'p_email' => $identity['email'],
                    'a_tok' => $accessTokenEnc,
                    'r_tok' => $refreshTokenEnc,
                    't_type' => $tokenResponse->tokenType,
                    'scopes' => json_encode($tokenResponse->scopes, JSON_THROW_ON_ERROR),
                    'exp_at' => $expiresAt,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $connectionId = (int) $this->db->lastInsertId();
        }

        $connection = $this->getConnection($connectionId, $requestingUserId);
        if ($connection === null) {
            throw new OAuthException('OAuth bağlantı kaydı yüklenemedi.');
        }

        return $connection;
    }

    /**
     * Retrieves decrypted access token in-memory.
     * Automatically triggers token refresh if expired.
     */
    public function getValidAccessToken(int $connectionId, int $requestingUserId): string
    {
        $row = $this->db->fetch(
            'SELECT * FROM oauth_connections WHERE id = :id AND provider = :provider',
            [
                'id' => $connectionId,
                'provider' => self::PROVIDER,
            ]
        );

        if ($row === null) {
            throw new OAuthException('OAuth bağlantısı bulunamadı.');
        }

        if ((int) $row['user_id'] !== $requestingUserId) {
            throw new OAuthException('Bu OAuth bağlantısına erişim yetkiniz yok.');
        }

        if ($row['status'] === 'revoked') {
            throw new OAuthTokenExpiredException('OAuth bağlantı izni kaldırılmıştır (Revoked).');
        }

        $expiresAtTimestamp = strtotime((string) $row['expires_at']);
        // 60 saniye toleransla token süresi dolmuşsa yenile
        if ($expiresAtTimestamp === false || $expiresAtTimestamp <= time() + 60) {
            $this->refreshAccessToken($connectionId, $requestingUserId);
            $row = $this->db->fetch(
                'SELECT * FROM oauth_connections WHERE id = :id',
                ['id' => $connectionId]
            );
            if ($row === null) {
                throw new OAuthException('Yenilenen bağlantı kaydı bulunamadı.');
            }
        }

        return $this->encryption->decrypt((string) $row['access_token_encrypted']);
    }

    /**
     * Refreshes access token using the encrypted refresh token.
     */
    public function refreshAccessToken(int $connectionId, int $requestingUserId): OAuthConnection
    {
        $row = $this->db->fetch(
            'SELECT * FROM oauth_connections WHERE id = :id AND provider = :provider',
            [
                'id' => $connectionId,
                'provider' => self::PROVIDER,
            ]
        );

        if ($row === null) {
            throw new OAuthException('OAuth bağlantısı bulunamadı.');
        }

        if ((int) $row['user_id'] !== $requestingUserId) {
            throw new OAuthException('Bu OAuth bağlantısına erişim yetkiniz yok.');
        }

        if ($row['status'] === 'revoked') {
            throw new OAuthTokenExpiredException('OAuth bağlantısı iptal edilmiştir; yenilenemez.');
        }

        $refreshTokenEnc = (string) $row['refresh_token_encrypted'];
        if ($refreshTokenEnc === '') {
            throw new OAuthException('Yenileme tokenı (refresh_token) mevcut değil.');
        }

        $refreshToken = $this->encryption->decrypt($refreshTokenEnc);

        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query($params),
        ];

        try {
            $response = $this->httpClient->request('POST', $this->tokenEndpoint, $options);
        } catch (Throwable $e) {
            throw new OAuthException('OAuth token yenileme isteği başarısız oldu: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new OAuthException(
                'Sağlayıcı token yenilemeyi reddetti (HTTP ' . $response->getStatusCode() . ').'
            );
        }

        /** @var mixed $body */
        $body = json_decode($response->getBody(), true);
        if (!is_array($body) || !isset($body['access_token'], $body['expires_in'])) {
            throw new OAuthException('Token yenileme yanıtı geçersiz formatta.');
        }

        $newAccessTokenEnc = $this->encryption->encrypt((string) $body['access_token']);
        $newExpiresAt = date('Y-m-d H:i:s', time() + (int) $body['expires_in']);
        $now = date('Y-m-d H:i:s');

        // Yeni refresh token dönmüşse güncelle, dönmemişse eskisini koru
        $finalRefreshEnc = isset($body['refresh_token']) && is_string($body['refresh_token'])
            ? $this->encryption->encrypt($body['refresh_token'])
            : $refreshTokenEnc;

        $this->db->execute(
            'UPDATE oauth_connections SET access_token_encrypted = :a_tok, '
            . 'refresh_token_encrypted = :r_tok, expires_at = :exp_at, updated_at = :now '
            . 'WHERE id = :id',
            [
                'a_tok' => $newAccessTokenEnc,
                'r_tok' => $finalRefreshEnc,
                'exp_at' => $newExpiresAt,
                'now' => $now,
                'id' => $connectionId,
            ]
        );

        $updated = $this->getConnection($connectionId, $requestingUserId);
        if ($updated === null) {
            throw new OAuthException('Güncellenen OAuth bağlantısı okunamadı.');
        }

        return $updated;
    }

    /**
     * Revokes Google OAuth access.
     * Calls Google's revocation endpoint, wipes tokens from local database,
     * marks status as revoked, and ensures subsequent access fails closed.
     */
    public function revokeAccess(int $connectionId, int $requestingUserId): bool
    {
        $row = $this->db->fetch(
            'SELECT * FROM oauth_connections WHERE id = :id AND provider = :provider',
            [
                'id' => $connectionId,
                'provider' => self::PROVIDER,
            ]
        );

        if ($row === null) {
            throw new OAuthException('İptal edilecek OAuth bağlantısı bulunamadı.');
        }

        if ((int) $row['user_id'] !== $requestingUserId) {
            throw new OAuthException('Bu bağlantıyı iptal etme yetkiniz bulunmamaktadır.');
        }

        // Zaten iptal edilmişse güvenle başarılı dön
        if ($row['status'] === 'revoked') {
            return true;
        }

        // İptal edilecek token'ı çöz (Öncelikle refresh token, yoksa access token)
        $tokenToRevoke = '';
        if (!empty($row['refresh_token_encrypted'])) {
            try {
                $tokenToRevoke = $this->encryption->decrypt((string) $row['refresh_token_encrypted']);
            } catch (Throwable) {
                $tokenToRevoke = '';
            }
        }

        if ($tokenToRevoke === '' && !empty($row['access_token_encrypted'])) {
            try {
                $tokenToRevoke = $this->encryption->decrypt((string) $row['access_token_encrypted']);
            } catch (Throwable) {
                $tokenToRevoke = '';
            }
        }

        // Google Revocation Endpoint'e çağrı yap
        if ($tokenToRevoke !== '') {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query(['token' => $tokenToRevoke]),
            ];

            try {
                $response = $this->httpClient->request('POST', $this->revokeEndpoint, $options);
                // Google revocation 200 döner
                if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 400) {
                    // 400 zaten geçersiz veya iptal edilmiş demek olabilir
                    throw new OAuthRevocationException(
                        'Sağlayıcı tarafında iptal çağrısı başarısız oldu (HTTP '
                        . $response->getStatusCode() . ').'
                    );
                }
            } catch (OAuthRevocationException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new OAuthRevocationException(
                    'Google iptal servisi ile iletişim kurulamadı: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $now = date('Y-m-d H:i:s');

        // Yerel veritabanında tokenları tamamen temizle ve revoked yap
        $this->db->execute(
            'UPDATE oauth_connections SET status = :status, revoked_at = :now, '
            . 'access_token_encrypted = :empty_tok, refresh_token_encrypted = :empty_tok, '
            . 'updated_at = :now WHERE id = :id',
            [
                'status' => 'revoked',
                'now' => $now,
                'empty_tok' => '',
                'id' => $connectionId,
            ]
        );

        return true;
    }

    /**
     * Retrieves connection record for user and site.
     */
    public function getConnection(int $connectionId, int $requestingUserId): ?OAuthConnection
    {
        $row = $this->db->fetch(
            'SELECT * FROM oauth_connections WHERE id = :id AND provider = :provider',
            [
                'id' => $connectionId,
                'provider' => self::PROVIDER,
            ]
        );

        if ($row === null || (int) $row['user_id'] !== $requestingUserId) {
            return null;
        }

        return $this->hydrateConnection($row);
    }

    /**
     * Finds active connection for site.
     */
    public function findConnectionBySite(int $siteId, int $userId): ?OAuthConnection
    {
        $row = $this->db->fetch(
            'SELECT * FROM oauth_connections '
            . 'WHERE site_id = :site_id AND user_id = :user_id AND provider = :provider AND status = :status '
            . 'ORDER BY id DESC LIMIT 1',
            [
                'site_id' => $siteId,
                'user_id' => $userId,
                'provider' => self::PROVIDER,
                'status' => 'active',
            ]
        );

        if ($row === null) {
            return null;
        }

        return $this->hydrateConnection($row);
    }

    /**
     * @param list<string> $scopes
     */
    private function validateScopes(array $scopes): void
    {
        foreach ($scopes as $scope) {
            if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
                throw new OAuthScopeException("İzin verilmeyen OAuth kapsamı talep edildi: '{$scope}'");
            }
        }
    }

    private function exchangeCodeForTokens(string $code, string $codeVerifier): OAuthTokenResponse
    {
        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ];

        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query($params),
        ];

        try {
            $response = $this->httpClient->request('POST', $this->tokenEndpoint, $options);
        } catch (Throwable $e) {
            throw new OAuthException('OAuth token uç noktasına ulaşılamadı: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new OAuthException(
                'OAuth sağlayıcısı token isteğini reddetti (HTTP ' . $response->getStatusCode() . ').'
            );
        }

        /** @var mixed $body */
        $body = json_decode($response->getBody(), true);
        if (!is_array($body) || !isset($body['access_token'], $body['expires_in'])) {
            throw new OAuthException('Token yanıt formatı geçersiz.');
        }

        /** @var list<string> $scopes */
        $scopes = isset($body['scope']) && is_string($body['scope'])
            ? explode(' ', $body['scope'])
            : self::ALLOWED_SCOPES;

        return new OAuthTokenResponse(
            accessToken: (string) $body['access_token'],
            refreshToken: isset($body['refresh_token']) && is_string($body['refresh_token'])
                ? $body['refresh_token']
                : null,
            expiresIn: (int) $body['expires_in'],
            tokenType: (string) ($body['token_type'] ?? 'Bearer'),
            scopes: $scopes,
            idToken: isset($body['id_token']) && is_string($body['id_token'])
                ? $body['id_token']
                : null
        );
    }

    /**
     * @return array{sub: ?string, email: ?string}
     */
    private function extractIdentityFromIdToken(?string $idToken): array
    {
        if ($idToken === null || $idToken === '') {
            return ['sub' => null, 'email' => null];
        }

        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return ['sub' => null, 'email' => null];
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            return ['sub' => null, 'email' => null];
        }

        /** @var mixed $claims */
        $claims = json_decode($payloadJson, true);
        if (!is_array($claims)) {
            return ['sub' => null, 'email' => null];
        }

        return [
            'sub' => isset($claims['sub']) && is_string($claims['sub']) ? $claims['sub'] : null,
            'email' => isset($claims['email']) && is_string($claims['email']) ? $claims['email'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActiveConnectionRecord(int $userId, ?int $siteId): ?array
    {
        if ($siteId !== null) {
            return $this->db->fetch(
                'SELECT * FROM oauth_connections '
                . 'WHERE user_id = :uid AND site_id = :sid AND provider = :prov AND status = :status '
                . 'ORDER BY id DESC LIMIT 1',
                [
                    'uid' => $userId,
                    'sid' => $siteId,
                    'prov' => self::PROVIDER,
                    'status' => 'active',
                ]
            );
        }

        return $this->db->fetch(
            'SELECT * FROM oauth_connections '
            . 'WHERE user_id = :uid AND site_id IS NULL AND provider = :prov AND status = :status '
            . 'ORDER BY id DESC LIMIT 1',
            [
                'uid' => $userId,
                'prov' => self::PROVIDER,
                'status' => 'active',
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateConnection(array $row): OAuthConnection
    {
        try {
            $decodedScopes = json_decode((string) $row['scopes'], true, 512, JSON_THROW_ON_ERROR);
            /** @var list<string> $scopes */
            $scopes = is_array($decodedScopes) ? array_values(array_map('strval', $decodedScopes)) : [];
        } catch (JsonException) {
            $scopes = [];
        }

        return new OAuthConnection(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            siteId: isset($row['site_id']) ? (int) $row['site_id'] : null,
            provider: (string) $row['provider'],
            providerUserId: isset($row['provider_user_id']) && is_string($row['provider_user_id'])
                ? $row['provider_user_id']
                : null,
            providerEmail: isset($row['provider_email']) && is_string($row['provider_email'])
                ? $row['provider_email']
                : null,
            tokenType: (string) $row['token_type'],
            scopes: $scopes,
            expiresAt: (string) $row['expires_at'],
            status: (string) $row['status'],
            revokedAt: isset($row['revoked_at']) && is_string($row['revoked_at'])
                ? $row['revoked_at']
                : null,
            createdAt: isset($row['created_at']) && is_string($row['created_at'])
                ? $row['created_at']
                : null,
            updatedAt: isset($row['updated_at']) && is_string($row['updated_at'])
                ? $row['updated_at']
                : null
        );
    }
}

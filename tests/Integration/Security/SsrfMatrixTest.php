<?php

declare(strict_types=1);

namespace SitePortal\Tests\Integration\Security;

use CurlHandle;
use PHPUnit\Framework\TestCase;
use SitePortal\Core\Http\SafeHttpClient;
use SitePortal\Core\Security\Exceptions\BlockedUrlException;
use SitePortal\Core\Security\Exceptions\InvalidUrlException;
use SitePortal\Core\Security\Exceptions\RedirectException;
use SitePortal\Core\Security\IpValidator;
use SitePortal\Core\Security\PinnedTarget;
use SitePortal\Core\Security\RedirectValidator;
use SitePortal\Core\Security\UrlSecurityPolicy;
use SitePortal\Core\Security\UrlValidator;
use SitePortal\Tests\Fixtures\MockDnsResolver;
use SitePortal\Tests\Fixtures\SsrfVectors;

/**
 * SSRF Savunma Matrisi Entegrasyon Testi
 *
 * SsrfVectors fikstüründeki tüm vektörlerin (34 bloklu IP, 6 public IP,
 * 19 bloklu URL, 27 atlatma URL'si, 7 meşru URL) IpValidator, UrlValidator
 * ve SafeHttpClient katmanlarında istisnasız engellendiğini kanıtlar.
 */
final class SsrfMatrixTest extends TestCase
{
    private MockDnsResolver $dns;
    private UrlSecurityPolicy $policy;
    private IpValidator $ipValidator;
    private UrlValidator $validator;
    private RedirectValidator $redirectValidator;

    protected function setUp(): void
    {
        $this->dns = new MockDnsResolver();
        $this->policy = new UrlSecurityPolicy();
        $this->ipValidator = new IpValidator($this->policy);
        $this->validator = new UrlValidator($this->policy, $this->ipValidator, $this->dns);
        $this->redirectValidator = new RedirectValidator($this->validator);

        // Standart test alan adlarını çözümlenebilir kıl
        $this->dns->setRecord('example.com', ['93.184.216.34']);
        $this->dns->setRecord('subdomain.example.com', ['93.184.216.34']);
        $this->dns->setRecord('evil-redirect.com', ['93.184.216.35']);
        $this->dns->setRecord('rebinding.example.com', ['93.184.216.36']);
        $this->dns->setRecord('internal-rebinding.target', ['169.254.169.254']);
    }

    public function testEveryBlockedIpIsRejectedByIpValidator(): void
    {
        $blockedIps = SsrfVectors::getBlockedIps();
        self::assertGreaterThanOrEqual(30, count($blockedIps));

        foreach ($blockedIps as $ip) {
            self::assertFalse(
                $this->ipValidator->isPublic($ip),
                "Bloklu IP public kabul edilmemelidir: {$ip}"
            );
            self::assertTrue(
                $this->ipValidator->isPrivateOrReserved($ip),
                "Bloklu IP özel/ayrılmış kabul edilmelidir: {$ip}"
            );
        }
    }

    public function testEveryPublicIpIsAcceptedByIpValidator(): void
    {
        $publicIps = SsrfVectors::getPublicIps();
        self::assertNotEmpty($publicIps);

        foreach ($publicIps as $ip) {
            self::assertTrue(
                $this->ipValidator->isPublic($ip),
                "Genel IP geçerli public kabul edilmelidir: {$ip}"
            );
            self::assertFalse(
                $this->ipValidator->isPrivateOrReserved($ip),
                "Genel IP özel/ayrılmış kabul edilmemelidir: {$ip}"
            );
        }
    }

    public function testEveryBlockedUrlIsRejectedByUrlValidator(): void
    {
        $blockedUrls = SsrfVectors::getBlockedUrls();
        self::assertGreaterThanOrEqual(15, count($blockedUrls));

        foreach ($blockedUrls as $url) {
            try {
                $this->validator->validate($url);
                self::fail("Bloklu URL için istisna fırlatılmalıdır: {$url}");
            } catch (BlockedUrlException $e) {
                self::assertContains($e->getErrorCode(), ['URL_BLOCKED', 'URL_PRIVATE_IP', 'URL_BLOCKED_PORT']);
            } catch (InvalidUrlException $e) {
                self::assertNotEmpty($e->getErrorCode());
            }
        }
    }

    public function testEveryInvalidOrEvasionUrlIsRejectedByUrlValidator(): void
    {
        $evasionUrls = SsrfVectors::getInvalidOrEvasionUrls();
        self::assertGreaterThanOrEqual(25, count($evasionUrls));

        foreach ($evasionUrls as $url) {
            try {
                $this->validator->validate($url);
                self::fail("Atlatma URL'si için istisna fırlatılmalıdır: {$url}");
            } catch (InvalidUrlException $e) {
                self::assertNotEmpty($e->getErrorCode(), "InvalidUrlException hata kodu boş olamaz: {$url}");
            } catch (BlockedUrlException $e) {
                self::assertNotEmpty($e->getErrorCode(), "BlockedUrlException hata kodu boş olamaz: {$url}");
            }
        }
    }

    public function testEveryLegitimatePublicUrlIsAcceptedByUrlValidator(): void
    {
        $legitimateUrls = SsrfVectors::getLegitimatePublicUrls();
        self::assertNotEmpty($legitimateUrls);

        foreach ($legitimateUrls as $url) {
            $target = $this->validator->validate($url);
            self::assertInstanceOf(PinnedTarget::class, $target);
            self::assertNotEmpty($target->getPrimaryIp());
            self::assertNotEmpty($target->getNormalizedUrl());
            self::assertTrue($this->ipValidator->isPublic($target->getPrimaryIp()));
        }
    }

    public function testSafeHttpClientBlocksDirectSsrfAttacksWithoutExecutingCurl(): void
    {
        $curlCallCount = 0;
        $mockExecutor = function (CurlHandle $ch) use (&$curlCallCount): array {
            $curlCallCount++;
            return [
                'errno' => 0,
                'error' => '',
                'code' => 200,
                'headers' => [],
                'body' => 'unexpected_success',
                'info' => ['size_download' => 18],
            ];
        };

        $client = new SafeHttpClient(
            $this->validator,
            $this->redirectValidator,
            $mockExecutor
        );

        $testTargets = [
            'http://127.0.0.1/admin',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/internal-status',
            'http://[::1]/',
            'http://[fd00::1]/',
            'http://localhost:80/admin',
        ];

        foreach ($testTargets as $targetUrl) {
            $initialCount = $curlCallCount;
            try {
                $client->get($targetUrl);
                self::fail("SafeHttpClient SSRF hedefini engellemelidir: {$targetUrl}");
            } catch (BlockedUrlException $e) {
                self::assertSame(
                    $initialCount,
                    $curlCallCount,
                    "SSRF engellendiğinde cURL kesinlikle tetiklenmemelidir: {$targetUrl}"
                );
                self::assertContains($e->getErrorCode(), ['URL_BLOCKED', 'URL_PRIVATE_IP']);
            }
        }
    }

    public function testSafeHttpClientBlocksDnsRebindingAttackOnRedirect(): void
    {
        $step = 0;
        $mockExecutor = function (CurlHandle $ch) use (&$step): array {
            $step++;
            if ($step === 1) {
                // İlk istek public bir domaine gidiyor, ancak yanıt 302 ile bulut metadata servisine yönlendiriyor
                return [
                    'errno' => 0,
                    'error' => '',
                    'code' => 302,
                    'headers' => [
                        'location' => ['http://169.254.169.254/latest/meta-data/iam/security-credentials/'],
                    ],
                    'body' => '',
                    'info' => ['size_download' => 0],
                ];
            }

            self::fail('SSRF yönlendirmesi cURL çalıştırmamalıdır!');
        };

        $client = new SafeHttpClient(
            $this->validator,
            $this->redirectValidator,
            $mockExecutor
        );

        $this->expectException(BlockedUrlException::class);
        $client->get('https://evil-redirect.com/redirect');
    }

    public function testSafeHttpClientBlocksDnsRebindingViaDomainRedirect(): void
    {
        $step = 0;
        $mockExecutor = function (CurlHandle $ch) use (&$step): array {
            $step++;
            if ($step === 1) {
                // Alan adı yönlendirmesi iç IP'ye çözümlenen başka bir alan adına yönlendiriyor
                return [
                    'errno' => 0,
                    'error' => '',
                    'code' => 302,
                    'headers' => [
                        'location' => ['http://internal-rebinding.target/secret'],
                    ],
                    'body' => '',
                    'info' => ['size_download' => 0],
                ];
            }

            self::fail('İç IP alan adı yönlendirmesinde cURL tetiklenmemelidir!');
        };

        $client = new SafeHttpClient(
            $this->validator,
            $this->redirectValidator,
            $mockExecutor
        );

        $this->expectException(BlockedUrlException::class);
        $client->get('https://rebinding.example.com/start');
    }

    public function testSafeHttpClientTerminatesRedirectLoops(): void
    {
        $mockExecutor = function (CurlHandle $ch): array {
            // Sonsuz döngü (kendi kendine yönlendirme)
            return [
                'errno' => 0,
                'error' => '',
                'code' => 301,
                'headers' => [
                    'location' => ['https://example.com/loop'],
                ],
                'body' => '',
                'info' => ['size_download' => 0],
            ];
        };

        $client = new SafeHttpClient(
            $this->validator,
            $this->redirectValidator,
            $mockExecutor,
            ['max_redirects' => 5]
        );

        $this->expectException(RedirectException::class);
        $client->get('https://example.com/loop');
    }

    public function testSafeHttpClientEnforcesMaxRedirectLimit(): void
    {
        $hop = 0;
        $mockExecutor = function (CurlHandle $ch) use (&$hop): array {
            $hop++;
            $nextHop = $hop + 1;
            return [
                'errno' => 0,
                'error' => '',
                'code' => 302,
                'headers' => [
                    'location' => ["https://example.com/hop-{$nextHop}"],
                ],
                'body' => '',
                'info' => ['size_download' => 0],
            ];
        };

        $client = new SafeHttpClient(
            $this->validator,
            $this->redirectValidator,
            $mockExecutor,
            ['max_redirects' => 3]
        );

        try {
            $client->get('https://example.com/hop-1');
            self::fail('Maksimum yönlendirme limiti aşıldığında istisna fırlatılmalıdır.');
        } catch (RedirectException $e) {
            self::assertSame(RedirectException::CODE_LIMIT_EXCEEDED, $e->getErrorCode());
            self::assertGreaterThanOrEqual(3, $hop);
        }
    }
}

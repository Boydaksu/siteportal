<?php

declare(strict_types=1);

namespace SitePortal\Core\Http;

use CurlHandle;
use SitePortal\Core\Config;
use SitePortal\Core\Http\Exceptions\BodyTooLargeException;
use SitePortal\Core\Http\Exceptions\HttpException;
use SitePortal\Core\Http\Exceptions\HttpRateLimitedException;
use SitePortal\Core\Http\Exceptions\HttpServerException;
use SitePortal\Core\Http\Exceptions\HttpTimeoutException;
use SitePortal\Core\Security\Exceptions\RedirectException;
use SitePortal\Core\Security\Exceptions\SecurityException;
use SitePortal\Core\Security\PinnedTarget;
use SitePortal\Core\Security\RedirectValidator;
use SitePortal\Core\Security\UrlValidator;

class SafeHttpClient implements HttpClientInterface
{
    private UrlValidator $urlValidator;
    private RedirectValidator $redirectValidator;
    private int $connectTimeout;
    private int $timeout;
    private int $maxResponseBytes;
    private int $maxDecompressedBytes;
    private int $maxRedirects;
    private string $userAgent;
    /**
     * @var (callable(CurlHandle): array<string, mixed>)|null
     */
    private $curlExecutor;

    /**
     * @param (callable(CurlHandle): array<string, mixed>)|null $curlExecutor
     * @param array<string, mixed> $config
     */
    public function __construct(
        ?UrlValidator $urlValidator = null,
        ?RedirectValidator $redirectValidator = null,
        ?callable $curlExecutor = null,
        array $config = []
    ) {
        $this->urlValidator = $urlValidator ?? new UrlValidator();
        $this->redirectValidator = $redirectValidator ?? new RedirectValidator();
        $this->curlExecutor = $curlExecutor;

        $configInstance = Config::getInstance();
        $this->connectTimeout = (int) (
            $config['connect_timeout'] ?? $configInstance->get('crawler.connect_timeout', 5)
        );
        $this->timeout = (int) ($config['timeout'] ?? $configInstance->get('crawler.timeout', 12));
        $this->maxResponseBytes = (int) ($config['max_response_bytes'] ?? $configInstance->get(
            'crawler.max_response_bytes',
            2 * 1024 * 1024
        ));
        $this->maxDecompressedBytes = (int) ($config['max_decompressed_bytes'] ?? $configInstance->get(
            'crawler.max_decompressed_bytes',
            8 * 1024 * 1024
        ));
        $this->maxRedirects = (int) ($config['max_redirects'] ?? $configInstance->get('crawler.max_redirects', 5));
        $this->userAgent = (string) ($config['user_agent'] ?? $configInstance->get(
            'crawler.user_agent',
            'SitePortalBot/0.1 (+https://github.com/Boydaksu/siteportal; bot@siteportal.local)'
        ));
    }

    public function get(string $url, array $options = []): HttpResponse
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * Executes a safe outbound request with DNS pinning, SSRF checks, redirect hop validation,
     * timeout enforcement, and gzip bomb/decompression size protection.
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     * @return HttpResponse
     * @throws SecurityException
     * @throws HttpException
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $currentTarget = $this->urlValidator->validate($url);

        $maxRedirects = (int) ($options['max_redirects'] ?? $this->maxRedirects);
        $timeout = (int) ($options['timeout'] ?? $this->timeout);
        $connectTimeout = (int) ($options['connect_timeout'] ?? $this->connectTimeout);
        $maxResponseBytes = (int) ($options['max_response_bytes'] ?? $this->maxResponseBytes);
        $maxDecompressedBytes = (int) ($options['max_decompressed_bytes'] ?? $this->maxDecompressedBytes);
        $userAgent = (string) ($options['user_agent'] ?? $this->userAgent);
        $throwOnError = (bool) ($options['throw_on_error'] ?? false);

        /** @var list<array{url: string, status: int}> $redirectChain */
        $redirectChain = [];
        /** @var list<string> $redirectHistory */
        $redirectHistory = [$currentTarget->getNormalizedUrl()];
        $currentHop = 0;

        while (true) {
            $response = $this->executeCurl(
                $method,
                $currentTarget,
                $timeout,
                $connectTimeout,
                $maxResponseBytes,
                $maxDecompressedBytes,
                $userAgent,
                $options,
                $redirectChain
            );

            if ($response->isRedirect() && $response->hasHeader('location')) {
                $location = (string) $response->getHeader('location');
                $redirectChain[] = [
                    'url' => $currentTarget->getNormalizedUrl(),
                    'status' => $response->getStatusCode(),
                ];

                $currentHop++;
                if ($currentHop > $maxRedirects) {
                    throw new RedirectException(
                        "Maksimum yönlendirme sınırı ({$maxRedirects}) aşıldı.",
                        RedirectException::CODE_LIMIT_EXCEEDED
                    );
                }

                // Resolves relative/absolute target, checks loop, and validates against SSRF & DNS
                $currentTarget = $this->redirectValidator->validateRedirect(
                    $currentTarget->getNormalizedUrl(),
                    $location,
                    $redirectHistory
                );

                // Subsequent redirect hops are always GET (per RFC 7231 / standard browser behavior for 301/302/303)
                if ($response->getStatusCode() === 303 || ($response->getStatusCode() <= 302 && $method !== 'HEAD')) {
                    $method = 'GET';
                }

                continue;
            }

            if ($throwOnError) {
                if ($response->isRateLimited()) {
                    throw new HttpRateLimitedException(
                        'Target server rate limited requests (HTTP 429).',
                        $response->getRetryAfter(),
                        429
                    );
                }
                if ($response->isServerError()) {
                    throw new HttpServerException(
                        'Target server returned error HTTP ' . $response->getStatusCode(),
                        $response->getStatusCode()
                    );
                }
            }

            return $response;
        }
    }

    /**
     * @param list<array{url: string, status: int}> $redirectChain
     * @param array<string, mixed> $options
     */
    private function executeCurl(
        string $method,
        PinnedTarget $target,
        int $timeout,
        int $connectTimeout,
        int $maxResponseBytes,
        int $maxDecompressedBytes,
        string $userAgent,
        array $options,
        array $redirectChain
    ): HttpResponse {
        $ch = curl_init();
        if ($ch === false) {
            throw new HttpException('Failed to initialize cURL handle.');
        }

        $headers = [];
        $headers[] = 'User-Agent: ' . $userAgent;
        $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.7';
        $headers[] = 'Accept-Encoding: gzip, deflate';
        $headers[] = 'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7';

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $val) {
                if (is_int($name)) {
                    $headers[] = (string) $val;
                } else {
                    $headers[] = $name . ': ' . $val;
                }
            }
        }

        $responseHeaders = [];
        $bodyBuffer = '';
        $decompressedBytes = 0;
        $contentLengthExceeded = false;
        $decompressedLimitExceeded = false;

        $targetUrl = $target->getNormalizedUrl();
        if ($targetUrl === '') {
            throw new HttpException('Normalized target URL cannot be empty.');
        }

        $effectiveUserAgent = $userAgent !== '' ? $userAgent : 'SitePortalBot';

        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $effectiveUserAgent);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accepts gzip/deflate; zlib decompresses into write callback
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Pin the connection to the validated IP to prevent DNS rebinding TOCTOU
        curl_setopt($ch, CURLOPT_RESOLVE, [$target->getCurlResolveOption()]);

        $upperMethod = strtoupper($method);
        if ($upperMethod === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($upperMethod !== 'GET' && $upperMethod !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
        }

        // Header parser and early Content-Length guard
        curl_setopt(
            $ch,
            CURLOPT_HEADERFUNCTION,
            function (
                $ch,
                string $headerLine
            ) use (
                &$responseHeaders,
                $maxResponseBytes,
                &$contentLengthExceeded
            ) {
                $len = strlen($headerLine);
                $trimmed = trim($headerLine);
                if ($trimmed === '') {
                    return $len;
                }

                if (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $name = strtolower(trim($name));
                    $value = trim($value);
                    $responseHeaders[$name][] = $value;

                    if ($name === 'content-length') {
                        $cl = (int) $value;
                        if ($cl > $maxResponseBytes) {
                            $contentLengthExceeded = true;
                            return 0; // Abort download immediately
                        }
                    }
                }

                return $len;
            }
        );

        // Write callback guard: tracks decompressed bytes (defending against gzip bombs)
        curl_setopt(
            $ch,
            CURLOPT_WRITEFUNCTION,
            function (
                $ch,
                string $data
            ) use (
                &$bodyBuffer,
                &$decompressedBytes,
                $maxDecompressedBytes,
                &$decompressedLimitExceeded
            ) {
                $len = strlen($data);
                $decompressedBytes += $len;

                if ($decompressedBytes > $maxDecompressedBytes) {
                    $decompressedLimitExceeded = true;
                    return 0; // Abort curl transfer immediately with CURLE_WRITE_ERROR
                }

                $bodyBuffer .= $data;
                return $len;
            }
        );

        if ($this->curlExecutor !== null) {
            $execResult = ($this->curlExecutor)($ch);
            curl_close($ch);

            $errno = $execResult['errno'];
            $error = $execResult['error'];
            $info = $execResult['info'];
            $responseCode = $execResult['code'];
            $responseHeaders = $execResult['headers'];
            $bodyBuffer = $execResult['body'];
            $decompressedBytes = strlen($bodyBuffer);
        } else {
            curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            $responseCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
        }

        if ($errno !== 0) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new HttpTimeoutException('HTTP request timed out after ' . $timeout . ' seconds.');
            }

            if ($errno === CURLE_WRITE_ERROR) {
                if ($decompressedLimitExceeded) {
                    throw new BodyTooLargeException(
                        'Decompressed response exceeded size limit of ' . $maxDecompressedBytes . ' bytes.',
                        $decompressedBytes,
                        $maxDecompressedBytes
                    );
                }
                if ($contentLengthExceeded) {
                    throw new BodyTooLargeException(
                        'Content-Length header exceeded size limit of ' . $maxResponseBytes . ' bytes.',
                        0,
                        $maxResponseBytes
                    );
                }
            }

            throw new HttpException('cURL error (' . $errno . '): ' . $error, 'HTTP_CLIENT_ERROR');
        }

        $rawDownloadSize = (int) ($info['size_download'] ?? 0);
        if ($rawDownloadSize > $maxResponseBytes) {
            throw new BodyTooLargeException(
                'Raw response body exceeded size limit of ' . $maxResponseBytes . ' bytes.',
                $rawDownloadSize,
                $maxResponseBytes
            );
        }

        if ($decompressedBytes > $maxDecompressedBytes) {
            throw new BodyTooLargeException(
                'Decompressed response body exceeded size limit of ' . $maxDecompressedBytes . ' bytes.',
                $decompressedBytes,
                $maxDecompressedBytes
            );
        }

        return new HttpResponse(
            $responseCode,
            $responseHeaders,
            $bodyBuffer,
            $target->getNormalizedUrl(),
            $redirectChain,
            $info
        );
    }
}

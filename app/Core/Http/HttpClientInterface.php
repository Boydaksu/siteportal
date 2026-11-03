<?php

declare(strict_types=1);

namespace SitePortal\Core\Http;

interface HttpClientInterface
{
    /**
     * Sends an HTTP GET request to the validated target URL.
     *
     * @param string $url The target URL.
     * @param array<string, mixed> $options Optional request options.
     * @return HttpResponse
     */
    public function get(string $url, array $options = []): HttpResponse;

    /**
     * Sends an HTTP request with the specified method.
     *
     * @param string $method HTTP method (GET, HEAD, POST).
     * @param string $url Target URL.
     * @param array<string, mixed> $options Optional request options.
     * @return HttpResponse
     */
    public function request(string $method, string $url, array $options = []): HttpResponse;
}

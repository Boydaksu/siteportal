<?php

declare(strict_types=1);

namespace SitePortal\Tests\Fixtures;

final class SsrfVectors
{
    /**
     * @return list<string> Blocked internal/private IPv4 and IPv6 raw addresses
     */
    public static function getBlockedIps(): array
    {
        return [
            // Loopback
            '127.0.0.1',
            '127.0.0.2',
            '127.255.255.254',
            '0.0.0.0',
            // Private RFC 1918
            '10.0.0.1',
            '10.255.255.254',
            '172.16.0.1',
            '172.31.255.254',
            '192.168.0.1',
            '192.168.1.100',
            // Link-Local / Cloud Metadata RFC 3927
            '169.254.169.254',
            '169.254.170.2',
            '169.254.1.1',
            // CGNAT RFC 6598
            '100.64.0.1',
            '100.127.255.254',
            // Benchmark RFC 2544
            '198.18.0.1',
            '198.19.255.254',
            // Documentation RFC 5737
            '192.0.2.1',
            '198.51.100.1',
            '203.0.113.1',
            // Multicast RFC 5771
            '224.0.0.1',
            '239.255.255.250',
            // Reserved RFC 1112
            '240.0.0.1',
            '255.255.255.255',
            // IPv6 Loopback
            '::1',
            '::',
            // IPv6 Unique Local Unicast (ULA) RFC 4193
            'fc00::1',
            'fd00::1',
            'fd12:3456:789a:1::1',
            // IPv6 Link-Local RFC 4291
            'fe80::1',
            'fe80::200:5aee:feaa:20a2',
            // IPv6 Documentation RFC 3849
            '2001:db8::1',
            // IPv4-mapped IPv6
            '::ffff:127.0.0.1',
            '::ffff:10.0.0.1',
            '::ffff:169.254.169.254',
            '::ffff:192.168.1.1',
        ];
    }

    /**
     * @return list<string> Valid public IPv4 and IPv6 addresses
     */
    public static function getPublicIps(): array
    {
        return [
            '93.184.216.34',     // example.com
            '8.8.8.8',            // Google DNS
            '1.1.1.1',            // Cloudflare DNS
            '142.250.180.206',    // google.com
            '2606:2800:220:1:248:1893:25c8:1946', // example.com IPv6
            '2001:4860:4860::8888',               // Google DNS IPv6
        ];
    }

    /**
     * @return list<string> Blocked URLs (SSRF / internal / metadata / reserved)
     */
    public static function getBlockedUrls(): array
    {
        return [
            'http://127.0.0.1/',
            'http://127.0.0.2:80/admin',
            'http://10.0.0.1/',
            'http://172.16.0.1/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://169.254.170.2/v2/metadata',
            'http://[::1]/',
            'http://[::ffff:127.0.0.1]/',
            'http://[fd00::1]/',
            'http://[fe80::1]/',
            'http://localhost/',
            'http://localhost:80/status',
            'http://metadata.google.internal/computeMetadata/v1/',
            'http://instance-data/latest/meta-data/',
            'http://router.local/',
            'http://intranet.internal/',
            'http://database.lan/',
            'http://test.example/',
            'http://onion-site.onion/',
        ];
    }

    /**
     * @return list<string> Malformed / evasion URLs that must be rejected with InvalidUrlException
     */
    public static function getInvalidOrEvasionUrls(): array
    {
        return [
            'http://0177.0.0.1/',                 // Octal IP
            'http://2130706433/',                 // Decimal integer IP
            'http://0x7f.0.0.1/',                 // Hex octet IP
            'http://0x7f000001/',                 // Hex integer IP
            'http://127.1/',                      // Incomplete IP notation
            'http://user:password@example.com/',  // Userinfo credentials
            'http://user@example.com/',           // Userinfo without pass
            'http://example.com@127.0.0.1/',      // Authority confusion with @
            'http://example.com\\evil.com/',      // Backslash confusion
            'ftp://example.com/',                 // Disallowed scheme
            'file:///etc/passwd',                 // Disallowed scheme
            'gopher://127.0.0.1:6379/_INFO',      // Disallowed scheme
            'dict://127.0.0.1:11211/stat',        // Disallowed scheme
            'data:text/html,<h1>test</h1>',       // Disallowed scheme
            'javascript:alert(1)',                // Disallowed scheme
            'http://example.com:22/',             // Blocked port (SSH)
            'http://example.com:25/',             // Blocked port (SMTP)
            'http://example.com:3306/',           // Blocked port (MySQL)
            'http://example.com:6379/',           // Blocked port (Redis)
            'http://example.com:8080/',           // Blocked non-standard port
            'http://example.com:8443/',           // Blocked non-standard port
            'http://',                            // Missing host
            'http:///path',                       // Missing host
            'http://example..com/',               // Consecutive dots
            'http://-example.com/',               // Leading hyphen
            'http://example-.com/',               // Trailing hyphen
            "http://example.com/\x00evil",        // Null byte
            'http://exa mple.com/',               // Space in host
        ];
    }

    /**
     * @return list<string> Legitimate public URLs
     */
    public static function getLegitimatePublicUrls(): array
    {
        return [
            'https://example.com',
            'https://example.com/',
            'http://example.com/blog/article-1',
            'https://subdomain.example.com:443/test?query=param&other=1#fragment',
            'http://93.184.216.34/',
            'https://93.184.216.34:443/seo-audit',
            'https://[2606:2800:220:1:248:1893:25c8:1946]/',
        ];
    }
}

<?php

declare(strict_types=1);

$allowedSchemesEnv = getenv('HTTP_ALLOWED_SCHEMES');
$allowedPortsEnv = getenv('HTTP_ALLOWED_PORTS');
$maxRedirectsEnv = getenv('HTTP_MAX_REDIRECTS');
$dnsTimeoutEnv = getenv('HTTP_DNS_TIMEOUT');
$allowPrivateEnv = getenv('HTTP_ALLOW_PRIVATE_IPS');

return [
    'allowed_schemes' => ($allowedSchemesEnv !== false && $allowedSchemesEnv !== '')
        ? explode(',', $allowedSchemesEnv)
        : ['http', 'https'],

    'allowed_ports' => ($allowedPortsEnv !== false && $allowedPortsEnv !== '')
        ? array_map('intval', explode(',', $allowedPortsEnv))
        : [80, 443],

    'max_redirects' => ($maxRedirectsEnv !== false && $maxRedirectsEnv !== '')
        ? (int) $maxRedirectsEnv
        : 5,

    'dns_timeout' => ($dnsTimeoutEnv !== false && $dnsTimeoutEnv !== '')
        ? (int) $dnsTimeoutEnv
        : 3,

    'allow_private_ips' => ($allowPrivateEnv !== false && ($allowPrivateEnv === 'true' || $allowPrivateEnv === '1')),

    'blocked_tlds' => [
        'arpa',
        'example',
        'home',
        'internal',
        'invalid',
        'lan',
        'local',
        'localhost',
        'onion',
        'test',
    ],

    'blocked_hostnames' => [
        'instance-data',
        'localhost',
        'metadata.google.internal',
    ],

    'blocked_ipv4_cidrs' => [
        '0.0.0.0/8',          // Current network (RFC 1122)
        '10.0.0.0/8',         // Private-Use (RFC 1918)
        '100.64.0.0/10',      // Shared Address Space / CGNAT (RFC 6598)
        '127.0.0.0/8',        // Loopback (RFC 1122)
        '169.254.0.0/16',     // Link-Local / Cloud Metadata (RFC 3927)
        '172.16.0.0/12',      // Private-Use (RFC 1918)
        '192.0.0.0/24',       // IETF Protocol Assignments (RFC 6890)
        '192.0.2.0/24',       // TEST-NET-1 (RFC 5737)
        '192.88.99.0/24',     // 6to4 Relay Anycast (RFC 7526)
        '192.168.0.0/16',     // Private-Use (RFC 1918)
        '198.18.0.0/15',      // Benchmarking (RFC 2544)
        '198.51.100.0/24',    // TEST-NET-2 (RFC 5737)
        '203.0.113.0/24',     // TEST-NET-3 (RFC 5737)
        '224.0.0.0/4',        // Multicast (RFC 5771)
        '240.0.0.0/4',        // Reserved for future use (RFC 1112)
        '255.255.255.255/32', // Limited Broadcast
    ],

    'blocked_ipv6_cidrs' => [
        '::/128',             // Unspecified
        '::1/128',            // Loopback
        '::ffff:0:0/96',      // IPv4-mapped IPv6
        '::ffff:0:0:0/96',    // IPv4-translated
        '64:ff9b::/96',       // IPv4/IPv6 translation
        '100::/64',           // Discard-Only (RFC 6666)
        '2001::/23',          // IETF Protocol Assignments
        '2001:db8::/32',      // Documentation (RFC 3849)
        '2002::/16',          // 6to4
        'fc00::/7',           // Unique Local Address / ULA (RFC 4193)
        'fe80::/10',          // Link-Local Unicast (RFC 4291)
        'ff00::/8',           // Multicast (RFC 4291)
    ],
];

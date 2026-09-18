<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

use Atro\Core\Exceptions\BadRequest;

/**
 * Validates URLs the server is about to fetch on behalf of a request.
 *
 * Two checks that have to be used together: assertFetchable() before the request is made,
 * and assertIpAllowed() on the address the connection actually landed on. The first one
 * rejects the obvious cases, the second one closes DNS rebinding, where the name resolves
 * to a public address for the check and to a private one for the transfer.
 */
class UrlGuard
{
    public const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Ranges that must never be reachable through a server-side fetch: loopback, link-local
     * (cloud metadata lives on 169.254.169.254), private and reserved networks.
     */
    private const BLOCKED_V4_RANGES
        = [
            ['0.0.0.0', 8],
            ['10.0.0.0', 8],
            ['100.64.0.0', 10],
            ['127.0.0.0', 8],
            ['169.254.0.0', 16],
            ['172.16.0.0', 12],
            ['192.0.0.0', 24],
            ['192.0.2.0', 24],
            ['192.168.0.0', 16],
            ['198.18.0.0', 15],
            ['198.51.100.0', 24],
            ['203.0.113.0', 24],
            ['224.0.0.0', 4],
            ['240.0.0.0', 4],
        ];

    private const BLOCKED_V6_RANGES
        = [
            ['::', 128],       // unspecified
            ['::1', 128],      // loopback
            ['::ffff:0:0', 96], // IPv4-mapped - handled separately, listed for completeness
            ['fc00::', 7],     // unique local
            ['fe80::', 10],    // link-local
            ['ff00::', 8],     // multicast
        ];

    /**
     * Keeps curl on http/https whichever libcurl is underneath - CURLOPT_PROTOCOLS is deprecated
     * in libcurl 7.85+, and its string replacement only exists when PHP was built against one.
     *
     * @param \CurlHandle $ch
     */
    public static function restrictProtocols($ch): void
    {
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');

            return;
        }

        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    /**
     * Hosts an installation legitimately fetches from inside its own network - an internal
     * DAM, a staging box - are named in the `fetchAllowedHosts` config parameter, and only
     * those skip the address check. Empty by default: reaching a private address is a
     * deliberate decision, not something that happens because nobody said otherwise.
     */
    public static function isHostAllowlisted(string $url, array $allowedHosts): bool
    {
        if (empty($allowedHosts)) {
            return false;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }

        $host = strtolower(trim($host, '[]'));

        foreach ($allowedHosts as $allowedHost) {
            if (!is_string($allowedHost)) {
                continue;
            }
            if ($host === strtolower(trim($allowedHost, '[]'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the URL is one the server may fetch and returns it normalized.
     *
     * @param string[] $allowedHosts hosts exempt from the address check, from `fetchAllowedHosts`
     *
     * @throws BadRequest
     */
    public static function assertFetchable(string $url, array $allowedHosts = []): string
    {
        $url = trim($url);

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new BadRequest('Invalid URL.');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new BadRequest('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new BadRequest('Only http and https URLs are allowed.');
        }

        // credentials in the URL are a redirect-laundering trick and have no legitimate use here
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new BadRequest('Credentials in the URL are not allowed.');
        }

        if (self::isHostAllowlisted($url, $allowedHosts)) {
            return $url;
        }

        foreach (self::resolveHost($parts['host']) as $ip) {
            self::assertIpAllowed($ip);
        }

        return $url;
    }

    /**
     * Checks a single address the connection resolved to. Called again once the transfer
     * has an address, so a name that changed its answer in between cannot slip through.
     *
     * @throws BadRequest
     */
    public static function assertIpAllowed(string $ip): void
    {
        $ip = trim($ip, '[]');

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            self::assertV4Allowed($ip);
            return;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new BadRequest('The URL does not resolve to a usable address.');
        }

        // ::ffff:127.0.0.1 and friends are IPv4 wearing an IPv6 hat
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16 && str_starts_with($packed, str_repeat("\x00", 10) . "\xff\xff")) {
            self::assertV4Allowed(inet_ntop(substr($packed, 12)));
            return;
        }

        foreach (self::BLOCKED_V6_RANGES as [$range, $bits]) {
            if ($range === '::ffff:0:0') {
                continue;
            }
            if (self::inRange($ip, $range, $bits)) {
                throw new BadRequest('The URL resolves to a non-routable address.');
            }
        }
    }

    /**
     * @throws BadRequest
     */
    private static function assertV4Allowed(string $ip): void
    {
        foreach (self::BLOCKED_V4_RANGES as [$range, $bits]) {
            if (self::inRange($ip, $range, $bits)) {
                throw new BadRequest('The URL resolves to a non-routable address.');
            }
        }
    }

    /**
     * @return string[]
     *
     * @throws BadRequest
     */
    private static function resolveHost(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            throw new BadRequest('The host of the URL could not be resolved.');
        }

        return array_unique($ips);
    }

    private static function inRange(string $ip, string $range, int $bits): bool
    {
        $ipBin    = @inet_pton($ip);
        $rangeBin = @inet_pton($range);

        if ($ipBin === false || $rangeBin === false || strlen($ipBin) !== strlen($rangeBin)) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $restBits  = $bits % 8;

        if ($fullBytes > 0 && strncmp($ipBin, $rangeBin, $fullBytes) !== 0) {
            return false;
        }

        if ($restBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $restBits)) - 1) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($rangeBin[$fullBytes]) & $mask);
    }
}

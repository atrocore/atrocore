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

namespace Atro\Core\Http;

use Psr\Http\Message\ServerRequestInterface;

class AuthCookie
{
    public const USERNAME = 'auth-username';
    public const TOKEN = 'auth-token';
    public const SESSION = 'auth-session';

    private const MAX_AGE = 1000 * 24 * 60 * 60;

    /**
     * @return string[]
     */
    public static function buildSetHeaders(ServerRequestInterface $request, string $siteUrl, string $userName, string $token): array
    {
        $secure = $request->getUri()->getScheme() === 'https' || str_starts_with($siteUrl, 'https://');

        return [
            self::build(self::USERNAME, $userName, self::MAX_AGE, $secure),
            self::build(self::TOKEN, $token, self::MAX_AGE, $secure),
            self::build(self::SESSION, '1', self::MAX_AGE, $secure, false),
        ];
    }

    /**
     * @return string[]
     */
    public static function buildClearHeaders(ServerRequestInterface $request, string $siteUrl): array
    {
        $secure = $request->getUri()->getScheme() === 'https' || str_starts_with($siteUrl, 'https://');

        return [
            self::build(self::USERNAME, '', 0, $secure),
            self::build(self::TOKEN, '', 0, $secure),
            self::build(self::SESSION, '', 0, $secure, false),
        ];
    }

    private static function build(string $name, string $value, int $maxAge, bool $secure, bool $httpOnly = true): string
    {
        $parts = [
            $name . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $maxAge > 0 ? time() + $maxAge : 0),
            'Max-Age=' . $maxAge,
            'Path=/',
            'SameSite=Strict',
        ];

        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}

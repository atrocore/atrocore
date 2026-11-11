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

namespace Atro\Core\OAuth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The cookies of the session of the user interface. The session cookie is not readable by scripts; the indicator
 * cookie only tells the user interface that a session exists.
 */
class SessionCookie
{
    public const SESSION = 'atro-session';
    public const INDICATOR = 'auth-session';
    public const LOGIN = 'atro-oauth-login';

    private const MAX_AGE = 1000 * 24 * 60 * 60;
    private const LOGIN_MAX_AGE = 10 * 60;

    public static function getSessionId(ServerRequestInterface $request): ?string
    {
        $value = $request->getCookieParams()[self::SESSION] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return string[]
     */
    public static function buildSessionHeaders(ServerRequestInterface $request, string $siteUrl, string $sessionId): array
    {
        $secure = self::isSecure($request, $siteUrl);

        return [
            self::build(self::SESSION, $sessionId, self::MAX_AGE, $secure, true),
            self::build(self::INDICATOR, '1', self::MAX_AGE, $secure, false),
            self::build(self::LOGIN, '', 0, $secure, true),
        ];
    }

    /**
     * @return string[]
     */
    public static function buildClearHeaders(ServerRequestInterface $request, string $siteUrl): array
    {
        $secure = self::isSecure($request, $siteUrl);

        return [
            self::build(self::SESSION, '', 0, $secure, true),
            self::build(self::INDICATOR, '', 0, $secure, false),
        ];
    }

    public static function buildLoginHeader(ServerRequestInterface $request, string $siteUrl, string $value): string
    {
        return self::build(self::LOGIN, $value, self::LOGIN_MAX_AGE, self::isSecure($request, $siteUrl), true);
    }

    private static function isSecure(ServerRequestInterface $request, string $siteUrl): bool
    {
        return $request->getUri()->getScheme() === 'https' || str_starts_with($siteUrl, 'https://');
    }

    private static function build(string $name, string $value, int $maxAge, bool $secure, bool $httpOnly): string
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

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
 * The cookies of the sign-in and of the session of the user interface. The cookies with data are encrypted and not
 * readable by scripts; the indicator cookies only tell the user interface what to show: the session cookie that a
 * session exists, the authorization request cookie that an authorization request waits for the user, the error cookie
 * why the last sign-in at an external identity provider failed.
 */
class SessionCookie
{
    public const SESSION = 'atro-session';
    public const INDICATOR = 'auth-session';
    public const EXTERNAL_SIGN_IN = 'atro-external-sign-in';
    public const AUTHORIZATION_REQUEST = 'atro-authorization-request';
    public const AUTHORIZATION_REQUEST_INDICATOR = 'auth-request';
    public const SIGN_IN_ERROR = 'auth-error';

    private const MAX_AGE = 1000 * 24 * 60 * 60;
    private const LOGIN_MAX_AGE = 10 * 60;
    private const SIGN_IN_ERROR_MAX_AGE = 60;

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

    /**
     * The cookie that keeps the authorization request while the user signs in at an external identity provider. The
     * provider returns by a cross-site navigation, so it is "Lax".
     */
    public static function buildExternalSignInHeader(ServerRequestInterface $request, string $siteUrl, string $value): string
    {
        return self::build(self::EXTERNAL_SIGN_IN, $value, self::LOGIN_MAX_AGE, self::isSecure($request, $siteUrl), true, 'Lax');
    }

    /**
     * The cookies that keep the authorization request while the user signs in, also at an external identity provider,
     * which returns by a cross-site navigation, so they are "Lax".
     *
     * @return string[]
     */
    public static function buildAuthorizationRequestHeaders(ServerRequestInterface $request, string $siteUrl, string $value): array
    {
        $secure = self::isSecure($request, $siteUrl);

        return [
            self::build(self::AUTHORIZATION_REQUEST, $value, self::LOGIN_MAX_AGE, $secure, true, 'Lax'),
            self::build(self::AUTHORIZATION_REQUEST_INDICATOR, '1', self::LOGIN_MAX_AGE, $secure, false, 'Lax'),
        ];
    }

    /**
     * @return string[]
     */
    public static function buildAuthorizationRequestClearHeaders(ServerRequestInterface $request, string $siteUrl): array
    {
        $secure = self::isSecure($request, $siteUrl);

        return [
            self::build(self::AUTHORIZATION_REQUEST, '', 0, $secure, true, 'Lax'),
            self::build(self::AUTHORIZATION_REQUEST_INDICATOR, '', 0, $secure, false, 'Lax'),
        ];
    }

    /**
     * The message of a failed sign-in at an external identity provider for the sign-in form, which removes the cookie.
     */
    public static function buildSignInErrorHeader(ServerRequestInterface $request, string $siteUrl, string $message): string
    {
        return self::build(self::SIGN_IN_ERROR, $message, self::SIGN_IN_ERROR_MAX_AGE, self::isSecure($request, $siteUrl), false, 'Lax');
    }

    public static function buildExternalSignInClearHeader(ServerRequestInterface $request, string $siteUrl): string
    {
        return self::build(self::EXTERNAL_SIGN_IN, '', 0, self::isSecure($request, $siteUrl), true, 'Lax');
    }

    private static function isSecure(ServerRequestInterface $request, string $siteUrl): bool
    {
        return $request->getUri()->getScheme() === 'https' || str_starts_with($siteUrl, 'https://');
    }

    private static function build(string $name, string $value, int $maxAge, bool $secure, bool $httpOnly, string $sameSite = 'Strict'): string
    {
        $parts = [
            $name . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $maxAge > 0 ? time() + $maxAge : 0),
            'Max-Age=' . $maxAge,
            'Path=/',
            'SameSite=' . $sameSite,
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

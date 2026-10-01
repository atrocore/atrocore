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

use Atro\Core\Exceptions\Error;

/**
 * Passwords are stored as argon2id(hmac-sha256(pepper, password)). The per-hash salt and the
 * argon2id parameters live in the hash itself; the pepper is an installation secret kept in
 * config.php, so a database dump alone is not enough to verify or brute-force a password.
 */
class PasswordHash
{
    public const PEPPER_CONFIG_KEY = 'passwordPepper';

    /**
     * Legacy: marks hashes of the old scheme (sha512-crypt of md5(password) with the global
     * passwordSalt), wrapped by the V2Dot5Dot2 migration as prefix . argon2id(hmac-sha256(pepper,
     * legacyHash)) because they cannot be converted without the plain password. verify() accepts
     * them, and needsRehash() reports them, so they are replaced with a regular hash on the next login.
     */
    private const LEGACY_PREFIX = '$legacy$';

    private const ALGO = PASSWORD_ARGON2ID;

    private const OPTIONS = [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 1,
    ];

    public function __construct(private readonly Config $config)
    {
    }

    public static function generatePepper(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $password): string
    {
        return password_hash($this->pepper($password), self::ALGO, self::OPTIONS);
    }

    public function verify(string $password, ?string $hash): bool
    {
        if (empty($hash)) {
            return false;
        }

        if (str_starts_with($hash, self::LEGACY_PREFIX)) {
            return password_verify(
                $this->pepper($this->legacyHash($password)),
                substr($hash, strlen(self::LEGACY_PREFIX))
            );
        }

        return password_verify($this->pepper($password), $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return str_starts_with($hash, self::LEGACY_PREFIX) || password_needs_rehash($hash, self::ALGO, self::OPTIONS);
    }

    private function pepper(string $value): string
    {
        $pepper = $this->config->get(self::PEPPER_CONFIG_KEY);
        if (empty($pepper) || !is_string($pepper)) {
            throw new Error('Option "' . self::PEPPER_CONFIG_KEY . '" does not exist in config.php');
        }

        return hash_hmac('sha256', $value, $pepper);
    }

    /**
     * Legacy: reproduces the old scheme's hash, needed to verify passwords with LEGACY_PREFIX until
     * their owners log in again. Kept exactly as it was, including the salt
     * stripping, which is a no-op for salts longer than the 16 characters sha512-crypt keeps.
     */
    private function legacyHash(string $password): string
    {
        $salt = $this->config->get('passwordSalt');
        if (!isset($salt)) {
            throw new Error('Option "passwordSalt" does not exist in config.php');
        }

        $salt = '$6$' . $salt . '$';

        return str_replace($salt, '', crypt(md5($password), $salt));
    }
}

<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot5Dot2 extends Base
{
    private const PEPPER_CONFIG_KEY = 'passwordPepper';

    private const LEGACY_PREFIX = '$legacy$';

    private const ENCRYPTION_KEY_CONFIG_KEY = 'encryptionKey';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-30 10:00:00');
    }

    public function up(): void
    {
        $this->copyEncryptionKey();

        $pepper = $this->createPepper();
        $this->wrapLegacyPasswordHashes($pepper);
        $this->hashAuthTokens();

        copy('vendor/atrocore/core/copy/public/.htaccess', 'public/.htaccess');
    }

    protected function copyEncryptionKey(): void
    {
        $salt = $this->getConfig()->get('passwordSalt');
        if (empty($salt) || !empty($this->getConfig()->get(self::ENCRYPTION_KEY_CONFIG_KEY))) {
            return;
        }

        $this->getConfig()->set(self::ENCRYPTION_KEY_CONFIG_KEY, $salt);

        // without it the stored credentials could not be decrypted any more
        if (!$this->getConfig()->save()) {
            throw new \RuntimeException('Failed to save "' . self::ENCRYPTION_KEY_CONFIG_KEY . '" to config.php');
        }
    }

    protected function createPepper(): string
    {
        $pepper = $this->getConfig()->get(self::PEPPER_CONFIG_KEY);
        if (!empty($pepper)) {
            return $pepper;
        }

        $pepper = bin2hex(random_bytes(32));
        $this->getConfig()->set(self::PEPPER_CONFIG_KEY, $pepper);

        // hashes wrapped with a pepper that is not persisted could never be verified again
        if (!$this->getConfig()->save()) {
            throw new \RuntimeException('Failed to save "' . self::PEPPER_CONFIG_KEY . '" to config.php');
        }

        return $pepper;
    }

    protected function wrapLegacyPasswordHashes(string $pepper): void
    {
        $table = $this->getDbal()->quoteIdentifier('user');

        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id, password')
            ->from($table)
            ->where('password IS NOT NULL')
            ->andWhere("password <> ''")
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            // already wrapped, or already an argon2id / other password_hash() hash
            if (str_starts_with($row['password'], self::LEGACY_PREFIX) || password_get_info($row['password'])['algo'] !== null) {
                continue;
            }

            $wrapped = self::LEGACY_PREFIX . password_hash(
                    hash_hmac('sha256', $row['password'], $pepper),
                    PASSWORD_ARGON2ID,
                    ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
                );

            $this->getDbal()->createQueryBuilder()
                ->update($table)
                ->set('password', ':password')
                ->where('id = :id')
                ->setParameter('password', $wrapped)
                ->setParameter('id', $row['id'])
                ->executeStatement();
        }
    }

    protected function hashAuthTokens(): void
    {
        $fromSchema = $this->getCurrentSchema();
        if (!$fromSchema->getTable('auth_token')->hasColumn('token')) {
            return;
        }

        // a null token gives a null hash, which also clears the copied password hashes
        if ($this->isPgSQL()) {
            $this->getPDO()->exec("UPDATE auth_token SET hash = encode(sha256(convert_to(token, 'UTF8')), 'hex')");
        } else {
            $this->getPDO()->exec("UPDATE auth_token SET hash = SHA2(token, 256)");
        }
    }
}

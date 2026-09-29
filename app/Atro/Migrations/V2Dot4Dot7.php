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

use Doctrine\DBAL\ParameterType;
use Atro\Core\Migration\Base;

/**
 * V2Dot2Dot21 added a unique index on user.(user_name, deleted), but an installation that already
 * had more than one 'system' user row (from before that username was enforced unique) never gets
 * the index created - its own attempt fails silently, and every subsequent sql-diff-run (driven by
 * userName's "unique": true in entityDefs) keeps retrying it and failing the same way. This
 * renames every duplicate down to one per deleted-state, then (re)creates the index directly.
 */
class V2Dot4Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-29 10:00:00');
    }

    public function up(): void
    {
        foreach ([false, true] as $deleted) {
            $this->deduplicateSystemUsers($deleted);
        }

        $this->createUniqueUserNameIndex();
    }

    protected function createUniqueUserNameIndex(): void
    {
        if ($this->isPgSQL()) {
            $this->exec('CREATE UNIQUE INDEX UNIQ_8D93D64924A232CFEB3B4E33 ON "user" (user_name, deleted)');
        } else {
            $this->exec('CREATE UNIQUE INDEX UNIQ_8D93D64924A232CFEB3B4E33 ON user (user_name, deleted)');
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function deduplicateSystemUsers(bool $deleted): void
    {
        try {
            $rows = $this->getDbal()->createQueryBuilder()
                ->select('id')
                ->from($this->getDbal()->quoteIdentifier('user'))
                ->where('user_name = :userName')
                ->andWhere('deleted = :deleted')
                ->setParameter('userName', 'system')
                ->setParameter('deleted', $deleted, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $ids = array_column($rows, 'id');
            if (count($ids) <= 1) {
                return;
            }

            // keep whichever duplicate the config already points to (only meaningful for the
            // non-deleted group); otherwise just keep the first one
            $systemUserId = (string)($this->getConfig()->get('systemUserId') ?? '');
            $keepId = !$deleted && in_array($systemUserId, $ids, true) ? $systemUserId : $ids[0];

            $suffix = 1;
            foreach ($ids as $id) {
                if ($id === $keepId) {
                    continue;
                }

                $this->getDbal()->createQueryBuilder()
                    ->update($this->getDbal()->quoteIdentifier('user'))
                    ->set('user_name', ':userName')
                    ->where('id = :id')
                    ->setParameter('userName', 'system_' . $suffix)
                    ->setParameter('id', $id)
                    ->executeStatement();

                $suffix++;
            }

            if (!$deleted && $systemUserId !== $keepId) {
                $this->getConfig()->set('systemUserId', $keepId);
                $this->getConfig()->save();
            }
        } catch (\Throwable $e) {
            // best-effort - one bad group must not abort the rest of the migration
        }
    }
}

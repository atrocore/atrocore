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

class V2Dot2Dot20 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-13 10:00:00');
    }

    public function up(): void
    {
        $systemUser = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getConnection()->quoteIdentifier('user'))
            ->where('user_name = :system')
            ->setParameter('system', 'system')
            ->fetchAssociative();

        if (!empty($systemUser)) {
            $this->getConfig()->set('systemUserId', $systemUser['id']);
            $this->getConfig()->save();
        }

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
}

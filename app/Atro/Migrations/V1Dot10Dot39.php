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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot39 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-03 15:15:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec('ALTER TABLE "user" ADD password_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
            $this->exec('ALTER TABLE "user" ADD password_updated_by_id VARCHAR(24) DEFAULT NULL');
        } else {
            $this->exec("ALTER TABLE user ADD password_updated_at DATETIME DEFAULT NULL, ADD password_updated_by_id VARCHAR(24) DEFAULT NULL");
        }

        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('user'))
            ->set('password_updated_at', 'created_at')
            ->set('password_updated_by_id', 'created_by_id')
            ->executeStatement();
    }

    public function down(): void
    {
        if ($this->isPgSQL()) {
            $this->exec('ALTER TABLE "user" DROP password_updated_at');
            $this->exec('ALTER TABLE "user" DROP password_updated_by_id');
        } else {
            $this->exec("ALTER TABLE user DROP password_updated_at, DROP password_updated_by_id");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}

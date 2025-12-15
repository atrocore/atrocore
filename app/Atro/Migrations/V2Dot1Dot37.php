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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class V2Dot1Dot37 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-15 17:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE action ADD search_entity VARCHAR(255) DEFAULT NULL");

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from($this->getConnection()->quoteIdentifier('action'))
                ->where('type in (:types)')
                ->andWhere('deleted=:false')
                ->andWhere('target_entity IS NOT NULL')
                ->andWhere('search_entity IS NULL')
                ->setParameter('types', ['update', 'email', 'delete'], Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $res = [];
        }

        foreach ($res as $row) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('action'))
                ->set('search_entity', ':searchEntity')
                ->where('id=:id')
                ->andWhere('source_entity IS NOT NULL')
                ->setParameter('id', $row['id'])
                ->setParameter('searchEntity', $row['target_entity'])
                ->executeQuery();
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}

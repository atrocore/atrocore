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
use Atro\Core\Utils\Metadata;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V2Dot1Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-25 10:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE attribute ADD is_protected BOOLEAN DEFAULT 'false' NOT NULL;");
            $this->exec("ALTER TABLE classification_attribute ADD is_read_only BOOLEAN DEFAULT 'false' NOT NULL;");
            $this->exec("ALTER TABLE classification_attribute ADD is_protected BOOLEAN DEFAULT 'false' NOT NULL");
        } else {
            $this->exec("ALTER TABLE attribute ADD is_protected TINYINT(1) DEFAULT '0' NOT NULL;");
            $this->exec("ALTER TABLE classification_attribute ADD is_read_only TINYINT(1) DEFAULT '0' NOT NULL, ADD is_protected TINYINT(1) DEFAULT '0' NOT NULL;");
        }

        // set isReadOnly to true for all classification attributes where attribute is read only
        $limit = 2000;
        $offset = 0;

        while (true) {

            $ids = $this->getConnection()->createQueryBuilder()
                ->from($this->getConnection()->quoteIdentifier('attribute'))
                ->select('id')
                ->where('is_read_only = :true and deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchFirstColumn();

            if (empty($ids)) {
                break;
            }

            $offset = $offset + $limit;

            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('classification_attribute'))
                ->set('is_read_only', ':true')
                ->where('attribute_id IN (:ids) and deleted = :false')
                ->setParameter('ids', $ids, Mapper::getParameterType($ids))
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeStatement();
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

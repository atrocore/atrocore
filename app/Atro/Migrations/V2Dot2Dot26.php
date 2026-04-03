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
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot26 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-12 10:00:00');
    }

    public function up(): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('master_data_entity')
            ->set('source_entity', ':null')
            ->setParameter('null', null, ParameterType::NULL)
            ->executeStatement();

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE master_data_entity ALTER source_entity TYPE TEXT");
            $this->exec("ALTER TABLE master_data_entity ALTER source_entity DROP DEFAULT");
            $this->exec("COMMENT ON COLUMN master_data_entity.source_entity IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("ALTER TABLE master_data_entity CHANGE source_entity source_entity LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
        }

        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from($this->getDbal()->quoteIdentifier('attribute'))
            ->where('type IN (:types)')
            ->andWhere('data IS NOT NULL')
            ->setParameter('types', ['link', 'linkMultiple'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $data = @json_decode($row['data'], true);

            if (!is_array($data)) {
                continue;
            }

            if (($data['field']['entityType'] ?? null) !== 'ExtensibleEnumOption') {
                continue;
            }

            $data['field']['entityField'] = 'name';

            $this->getDbal()->createQueryBuilder()
                ->update('attribute')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $row['id'])
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
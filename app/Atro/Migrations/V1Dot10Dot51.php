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
use Atro\NotificationTransport\NotificationOccurrence;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Util;

class V1Dot10Dot51 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-31 16:00:00');
    }

    public function up(): void
    {
        $duplicatedRecords = $this->getConnection()
            ->createQueryBuilder()
            ->from('user_followed_record')
            ->select("entity_id","entity_type","user_id")
            ->groupBy("entity_id","entity_type","user_id" )
            ->having('count(*)>1')
            ->fetchAllAssociative();


        foreach ($duplicatedRecords as $duplicateRecord) {
            $records = $this->getConnection()
                ->createQueryBuilder()
                ->from('user_followed_record')
                ->select("id")
                ->where('entity_id=:entityId and entity_type=:entityType and user_id=:userId')
                ->setParameter('entityId', $duplicateRecord['entity_id'])
                ->setParameter('entityType', $duplicateRecord['entity_type'])
                ->setParameter('userId', $duplicateRecord['user_id'])
                ->fetchAllAssociative();


            for($i = 1; $i < count($records); $i++) {
                $this->getConnection()
                    ->createQueryBuilder()
                    ->delete('user_followed_record')
                    ->where('id=:id')
                    ->setParameter('id', $records[$i]['id'])
                    ->executeStatement();
            }

        }

        $this->exec("CREATE UNIQUE INDEX IDX_USER_FOLLOWED_RECORD_UNIQUE ON user_followed_record (entity_id, entity_type, user_id);");
    }

    public function down(): void
    {

    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}

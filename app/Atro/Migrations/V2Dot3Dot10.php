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
use Atro\Core\Utils\Util;

class V2Dot3Dot10 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-18 17:00:00');
    }

    public function up(): void
    {
        $this->migrateMatchings();
        $this->migrateDerivativeMiddle();
    }

    public function migrateMatchings(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE matching ADD name VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE matching ADD code VARCHAR(255) DEFAULT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
        } else {
            $this->exec("ALTER TABLE matching ADD name VARCHAR(255) DEFAULT NULL, ADD code VARCHAR(255) DEFAULT NULL COLLATE `utf8_bin`");
            $this->exec("CREATE UNIQUE INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching (code, deleted)");
        }

        $this->getDbal()->createQueryBuilder()
            ->delete('matching')
            ->where('deleted = :true')
            ->setParameter('true', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->executeQuery();

        try {
            $res = $this->getDbal()->createQueryBuilder()
                ->select('*')
                ->from('matching')
                ->where('deleted = :false')
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable) {
            $res = [];
        }

        foreach ($res as $item) {
            $uuid = \Atro\Core\Utils\IdGenerator::uuid();

            $this->getDbal()->createQueryBuilder()
                ->update('matching')
                ->set('name', ':name')
                ->set('code', ':code')
                ->set('id', ':uuid')
                ->where('id = :id')
                ->setParameters([
                    'name' => $item['id'],
                    'code' => $item['id'],
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();

            $this->getDbal()->createQueryBuilder()
                ->update('matching_rule')
                ->set('matching_id', ':uuid')
                ->where('matching_id = :id')
                ->setParameters([
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();

            $this->getDbal()->createQueryBuilder()
                ->update('matched_record')
                ->set('matching_id', ':uuid')
                ->where('matching_id = :id')
                ->setParameters([
                    'id'   => $item['id'],
                    'uuid' => $uuid,
                ])
                ->executeQuery();
        }
    }

    public function migrateDerivativeMiddle(): void
    {
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (empty($scopeDefs['primaryEntityId'])) {
                continue;
            }

            $primaryEntity = $scopeDefs['primaryEntityId'];

            $entityDefs = $metadata->get("entityDefs.$primaryEntity");

            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                if ($fieldDefs['type'] === 'linkMultiple') {
                    $linkDefs = $entityDefs['links'][$fieldName] ?? null;

                    if (!empty($linkDefs['relationName'])) {
                        if ($linkDefs['relationName'] !== "{$linkDefs['entity']}Hierarchy") {
                            $newName = $scope . $linkDefs['entity'];

                            $i = 2;
                            while (!empty($data['entityDefs'][$newName])) {
                                $newName = $newName . $i;
                                $i++;
                            }

                            $oldName = Util::toUnderScore('derivativeMiddle_' . md5("{$linkDefs['relationName']}_$scope"));
                            $newName = Util::toUnderScore($newName);

                            $this->exec("ALTER TABLE $oldName RENAME TO $newName");
                        }
                    }
                }
            }
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
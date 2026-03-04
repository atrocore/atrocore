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

use Atro\Core\Application;
use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;

class V2Dot2Dot23 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-19 10:00:00');
    }

    public function up(): void
    {
        $entityNames = $this->getDbal()->createQueryBuilder()
            ->select('entity_name')
            ->distinct()
            ->from('cluster_item')
            ->fetchFirstColumn();

        foreach ($entityNames as $entityName) {
            $tableName = $this->getDbal()->quoteIdentifier(Util::toUnderScore(lcfirst($entityName)));

            try {
                $this->getDbal()->createQueryBuilder()
                    ->delete('cluster_item')
                    ->where("cluster_item.entity_name=:entityName AND NOT EXISTS (SELECT 1 FROM $tableName e WHERE e.id=cluster_item.entity_id and deleted=:false)")
                    ->setParameter('entityName', $entityName)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeQuery();
            } catch (\Exception $e) {
            }
        }

        $this->rebuildHierarchyRoutes();

        $this->migrateFileTypeIds();
    }

    private function rebuildHierarchyRoutes(): void
    {
        $container = (new Application())->getContainer();
        $em = $container->getEntityManager();
        $dbal = $container->getDbal();

        foreach ($container->getMetadata()->get("scopes") ?? [] as $entityName => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'Hierarchy') {
                try {
                    $tableName = Util::toUnderScore(lcfirst($entityName));

                    echo "Rebuilding routes for entity $entityName...\n";

                    $dbal->createQueryBuilder()
                        ->update($dbal->quoteIdentifier($tableName))
                        ->set('routes', ':null')
                        ->setParameter('null', null, ParameterType::NULL)
                        ->executeQuery();

                    /** @var \Atro\Core\Templates\Repositories\Hierarchy $repository */
                    $repository = $em->getRepository($entityName);

                    while (true) {
                        $res = $dbal->createQueryBuilder()
                            ->select('t.*')
                            ->from($dbal->quoteIdentifier($tableName), 't')
                            ->leftJoin('t', $tableName . '_hierarchy', 'h', 't.id=h.entity_id')
                            ->where('h.id IS NULL AND t.routes IS NULL')
                            ->andWhere('t.deleted = :false')
                            ->setParameter('false', false, ParameterType::BOOLEAN)
                            ->setFirstResult(0)
                            ->setMaxResults(20000)
                            ->fetchAllAssociative();

                        if (empty($res)) {
                            break;
                        }

                        foreach ($res as $row) {
                            $repository->buildRoutes($row['id']);
                        }
                    }
                } catch (\Exception $e) {
                    echo "Failed to rebuild routes for entity $entityName: " . $e->getMessage();
                }
            }
        }
    }

    private function migrateFileTypeIds(): void
    {
        $fileTypes = [
            'a_document'     => '019c320b-3b1d-707d-af8c-d011190bd712',
            'a_spreadsheet'  => '019c320b-5a35-71f4-bd7e-9673fca98b86',
            'a_image'        => '019c320b-77ba-73d3-8f1b-8346dce0f7bb',
            'a_favicon'      => '019c320b-8c5f-7374-880c-ce48237046cb',
            'a_audio'        => '019c320b-a0e6-7223-8bc4-b4ae7ca63e3c',
            'a_video'        => '019c320b-b727-70d0-95b1-175ec86ca367',
            'a_archive'      => '019c320b-cccd-7155-a5aa-f154ec2c3f62',
            'a_graphics'     => '019c320b-e4a1-71be-a909-310f11902d87',
            'a_presentation' => '019c320b-fa2b-7365-b8f0-585d6f9dc24f'
        ];

        foreach ($fileTypes as $key => $value) {
            $this->getDbal()->createQueryBuilder()
                ->update('file_type')
                ->set('id', ':id')
                ->where('file_type.id=:oldId')
                ->setParameter('id', $value)
                ->setParameter('oldId', $key)
                ->executeStatement();

            $this->getDbal()->createQueryBuilder()
                ->update('attribute')
                ->set('file_type_id', ':id')
                ->where('file_type_id=:oldId')
                ->setParameter('id', $value)
                ->setParameter('oldId', $key)
                ->executeStatement();

            $this->getDbal()->createQueryBuilder()
                ->update('file')
                ->set('type_id', ':id')
                ->where('type_id=:oldId')
                ->setParameter('id', $value)
                ->setParameter('oldId', $key)
                ->executeStatement();
        }

        $entityDefsDir = 'data/metadata/entityDefs';
        if (is_dir($entityDefsDir)) {
            foreach (scandir($entityDefsDir) as $file) {
                if (!str_ends_with($file, '.json')) {
                    continue;
                }

                $path = $entityDefsDir . '/' . $file;
                $data = json_decode(file_get_contents($path), true);

                if (empty($data['fields'])) {
                    continue;
                }

                $changed = false;
                foreach ($data['fields'] as $fieldName => &$fieldDef) {
                    if (isset($fieldDef['fileTypeId']) && isset($fileTypes[$fieldDef['fileTypeId']])) {
                        $fieldDef['fileTypeId'] = $fileTypes[$fieldDef['fileTypeId']];
                        $changed = true;
                    }
                }
                unset($fieldDef);

                if ($changed) {
                    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }
}

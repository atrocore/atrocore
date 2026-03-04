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
}

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
use Atro\Core\Utils\Util;
use Espo\ORM\EntityManager;

class V2Dot1Dot25 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-11-07 13:00:00');
    }

    public function up(): void
    {
        $container = (new \Atro\Core\Application())->getContainer();

        /** @var EntityManager $em */
        $em = $container->get('entityManager');

        /** @var Metadata $metadata */
        $metadata = $container->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $defs) {
            if (empty($defs['type'])) {
                continue;
            }

            if ($defs['type'] === 'Hierarchy') {
                $tableName = $this->getConnection()->quoteIdentifier(Util::toUnderScore(lcfirst($scope)));
                if ($this->isPgSQL()) {
                    $this->exec("ALTER TABLE $tableName ADD routes TEXT DEFAULT NULL");
                    $this->exec("COMMENT ON COLUMN $tableName.routes IS '(DC2Type:jsonArray)'");
                } else {
                    $this->exec("ALTER TABLE $tableName ADD routes LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
                }

                while (true) {
                    $res = $this->getConnection()->createQueryBuilder()
                        ->select('t.*')
                        ->from($tableName, 't')
                        ->leftJoin('t', Util::toUnderScore(lcfirst($scope)).'_hierarchy', 'h', 't.id=h.entity_id')
                        ->where('h.id IS NULL AND t.routes IS NULL')
                        ->setFirstResult(0)
                        ->setMaxResults(20000)
                        ->fetchAllAssociative();

                    if (empty($res)) {
                        break;
                    }

                    foreach ($res as $row) {
                        $em->getRepository($scope)->buildRoutes($row['id']);
                    }
                }
            }
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

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

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\Utils\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\EntityManager;

class RebuildHierarchyRoutesForEntity extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Rebuild routes for hierarchy entity.';
    }

    public function run(array $data): void
    {
        $entityName = $data['entityName'];

        if (!$this->rebuild($entityName)) {
            self::show("Only the 'Hierarchy' entity type can has routes.", self::ERROR, true);
        }

        self::show("Routes has been built successfully for the '$entityName'.", self::SUCCESS);
    }

    public function rebuild(string $entityName): bool
    {
        if ($this->getMetadata()->get("scopes.$entityName.type") !== 'Hierarchy') {
            return false;
        }

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('entityManager');

        /** @var Connection $conn */
        $conn = $this->getContainer()->get('connection');

        $tableName = Util::toUnderScore(lcfirst($entityName));

        $conn->createQueryBuilder()
            ->update($conn->quoteIdentifier($tableName))
            ->set('routes', ':null')
            ->setParameter('null', null, ParameterType::NULL)
            ->executeQuery();

        /** @var \Atro\Core\Templates\Repositories\Hierarchy $repository */
        $repository = $em->getRepository($entityName);

        while (true) {
            $res = $conn->createQueryBuilder()
                ->select('t.*')
                ->from($conn->quoteIdentifier($tableName), 't')
                ->leftJoin('t', $tableName.'_hierarchy', 'h', 't.id=h.entity_id')
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

        return true;
    }
}

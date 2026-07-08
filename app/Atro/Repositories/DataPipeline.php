<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class DataPipeline extends Base
{
    public static function getPipelinesWithSourceEntities(Connection $connection): array
    {
        try {
            return $connection->createQueryBuilder()
                ->select('s.staging_entity_id', 's.source_entity')
                ->from('source_to_staging_pipeline', 's')
                ->where('s.deleted = :false')
                ->andWhere('s.source_entity IS NOT NULL')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $stagingEntityId = $entity->get('stagingEntityId');
        if (!empty($stagingEntityId)) {
            $scopeDefs = $this->getMetadata()->get(['scopes', $stagingEntityId]) ?? [];
            if (empty($scopeDefs['primaryEntityId']) || ($scopeDefs['role'] ?? null) !== 'contributor') {
                throw new BadRequest("Entity '$stagingEntityId' is not a valid staging entity.");
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew() || $entity->isAttributeChanged('sourceEntity')) {
            $this->getDataManager()->rebuild();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getDataManager()->rebuild();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}

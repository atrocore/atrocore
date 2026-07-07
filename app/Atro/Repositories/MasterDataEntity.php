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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class MasterDataEntity extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entityName = $entity->get('entity');

            if (empty($entityName) || !$this->isEntityTypeAllowed((string)$entityName)) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('entityTypeInvalid', 'exceptions', 'MasterDataEntity'),
                        (string)$entityName
                    )
                );
            }

            $existing = $this->getDbal()->createQueryBuilder()
                ->select('id, deleted')
                ->from($this->getDbal()->quoteIdentifier('master_data_entity'))
                ->where('id = :id')
                ->setParameter('id', $entityName)
                ->fetchAssociative();

            if (!empty($existing)) {
                if (empty($existing['deleted'])) {
                    throw new BadRequest(
                        sprintf(
                            $this->getLanguage()->translate('masterDataEntityAlreadyExists', 'exceptions', 'MasterDataEntity'),
                            $entityName
                        )
                    );
                }

                // remove the soft-deleted record with the same ID to avoid a primary key collision
                $this->getDbal()->createQueryBuilder()
                    ->delete($this->getDbal()->quoteIdentifier('master_data_entity'))
                    ->where('id = :id')
                    ->andWhere('deleted = :true')
                    ->setParameter('id', $entityName)
                    ->setParameter('true', true, ParameterType::BOOLEAN)
                    ->executeQuery();
            }

            $entity->id = $entityName;
        }

        parent::beforeSave($entity, $options);
    }

    protected function isEntityTypeAllowed(string $entityName): bool
    {
        if ($entityName === 'MasterDataEntity') {
            return false;
        }

        $scopeDefs = $this->getMetadata()->get(['scopes', $entityName]);

        return !empty($scopeDefs)
            && in_array($scopeDefs['type'] ?? '', ['Base', 'Hierarchy'])
            && ($scopeDefs['customizable'] ?? true) !== false;
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $pipelines = $this->getEntityManager()
            ->getRepository('SourceToStagingPipeline')
            ->where(['stagingEntityId' => $entity->get('id')])
            ->find();

        foreach ($pipelines as $pipeline) {
            $this->getEntityManager()->removeEntity($pipeline);
        }
    }
}
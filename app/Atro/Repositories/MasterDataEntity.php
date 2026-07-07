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
    public function getByEntityName(?string $entityName): ?Entity
    {
        if (empty($entityName)) {
            return null;
        }

        return $this->where(['name' => $entityName])->findOne();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entityName = $entity->get('name');

            if (empty($entityName) || !$this->isEntityTypeAllowed((string)$entityName)) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('entityTypeInvalid', 'exceptions', 'MasterDataEntity'),
                        (string)$entityName
                    )
                );
            }

            if (!empty($this->getByEntityName($entityName))) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('masterDataEntityAlreadyExists', 'exceptions', 'MasterDataEntity'),
                        $entityName
                    )
                );
            }

            // remove a soft-deleted record with the same name to avoid a unique index collision
            $this->getDbal()->createQueryBuilder()
                ->delete($this->getDbal()->quoteIdentifier('master_data_entity'))
                ->where('name = :name')
                ->andWhere('deleted = :true')
                ->setParameter('name', $entityName)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeQuery();
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
            && ($scopeDefs['customizable'] ?? true) !== false
            && empty($scopeDefs['primaryEntityId']);
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

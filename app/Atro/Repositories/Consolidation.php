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

class Consolidation extends Base
{
    public function getByEntityName(?string $entityName): ?Entity
    {
        if (empty($entityName)) {
            return null;
        }

        return $this->where(['name' => $entityName])->findOne();
    }

    public function getContributorEntityName(string $masterEntityName): ?string
    {
        foreach ($this->getMetadata()->get('scopes', []) as $scopeName => $scopeDefs) {
            if (($scopeDefs['primaryEntityId'] ?? null) === $masterEntityName && ($scopeDefs['role'] ?? null) === 'contributor') {
                return $scopeName;
            }
        }

        return null;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entityName = $entity->get('name');

            if (empty($entityName) || empty($this->getContributorEntityName((string)$entityName))) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('entityTypeInvalid', 'exceptions', 'Consolidation'),
                        (string)$entityName
                    )
                );
            }

            if (!empty($this->getByEntityName($entityName))) {
                throw new BadRequest(
                    sprintf(
                        $this->getLanguage()->translate('consolidationAlreadyExists', 'exceptions', 'Consolidation'),
                        $entityName
                    )
                );
            }

            // remove a soft-deleted record with the same name to avoid a unique index collision
            $this->getDbal()->createQueryBuilder()
                ->delete($this->getDbal()->quoteIdentifier('consolidation'))
                ->where('name = :name')
                ->andWhere('deleted = :true')
                ->setParameter('name', $entityName)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeQuery();
        }

        parent::beforeSave($entity, $options);
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

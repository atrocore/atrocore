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
use Espo\ORM\Entity;

class DataPipeline extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $sourceEntityId = $entity->get('sourceEntityId');
        if (!empty($sourceEntityId) && ($entity->isNew() || $entity->isAttributeChanged('sourceEntityId'))) {
            $scopeDefs = $this->getMetadata()->get(['scopes', $sourceEntityId]) ?? [];

            if (!in_array($scopeDefs['type'] ?? null, ['Base', 'Hierarchy'], true)) {
                throw new BadRequest(
                    sprintf($this->translateException('sourceEntityTypeNotAllowed'), $sourceEntityId)
                );
            }

            if (!empty($scopeDefs['primaryEntityId']) && in_array($scopeDefs['role'] ?? null, ['contributor', 'changeRequest'], true)) {
                throw new BadRequest(
                    sprintf($this->translateException('sourceEntityCannotBeContributorOrChangeRequestDerivative'), $sourceEntityId)
                );
            }
        }

        $targetEntityId = $entity->get('targetEntityId');
        if (!empty($targetEntityId) && ($entity->isNew() || $entity->isAttributeChanged('targetEntityId'))) {
            if ($this->isPrimaryOfContributorDerivative($targetEntityId)) {
                throw new BadRequest(
                    sprintf($this->translateException('targetEntityCannotBePrimaryOfContributorDerivative'), $targetEntityId)
                );
            }
        }

        if ($entity->isAttributeChanged('sourceEntityId') || $entity->isAttributeChanged('targetEntityId')) {
            if ($sourceEntityId === $targetEntityId) {
                throw new BadRequest($this->translateException('sourceAndTargetEntityCannotBeSame'));
            }

            $hashData = [$entity->get('sourceEntityId'), $entity->get('targetEntityId')];
            sort($hashData);

            $entity->set('hash', md5(implode('|', $hashData)));

            $where = ['hash' => $entity->get('hash')];
            if (!$entity->isNew()) {
                $where['id!='] = $entity->get('id');
            }

            if (!empty($this->where($where)->findOne())) {
                throw new BadRequest($this->translateException('dataPipelineAlreadyExists'));
            }
        }
    }

    protected function isPrimaryOfContributorDerivative(string $entityName): bool
    {
        foreach ($this->getMetadata()->get('scopes', []) as $scopeDefs) {
            if (($scopeDefs['primaryEntityId'] ?? null) === $entityName && ($scopeDefs['role'] ?? null) === 'contributor') {
                return true;
            }
        }

        return false;
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

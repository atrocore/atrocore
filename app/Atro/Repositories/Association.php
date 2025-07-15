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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

class Association extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (empty($entity->get('isActive')) && $this->hasRecords($entity, true)) {
            throw new BadRequest($this->getLanguage()->translate('youCanNotDeactivateAssociationWithActiveRecords', 'exceptions', 'Association'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('default') && !empty($entity->get('default'))) {
            $others = $this->where([
                'entityId' => $entity->get('entityId'),
                'id!='     => $entity->get('id'),
                'default'  => true
            ])->find() ?? [];

            foreach ($others as $item) {
                $item->set('default', false);
                $this->save($item);
            }
        }

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->hasRecords($entity)) {
            throw new BadRequest($this->getLanguage()->translate('associationIsLinkedWithRecords', 'exceptions', 'Association'));
        }
    }


    /**
     * Is association used in Entity record(s)
     *
     * @param Entity $entity
     * @param bool   $isActive
     *
     * @return bool
     */
    protected function hasRecords(Entity $entity, bool $isActive = false): bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $scope = $entity->get('entityId');
        $table = Util::toUnderScore($scope);

        $qb = $connection->createQueryBuilder()
            ->select('ar.id')
            ->from(Util::toUnderScore("Associated$scope"), 'ar')
            ->innerJoin('ar', $connection->quoteIdentifier($table), 'rm', "rm.id = ar.main_{$table}_id AND rm.deleted = :false")
            ->innerJoin('ar', $connection->quoteIdentifier($table), 'rr', "rr.id = ar.related_{$table}_id AND rr.deleted = :false")
            ->where('ar.deleted = :false')
            ->andWhere('ar.association_id = :associationId')
            ->setParameter('associationId', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
            ->setParameter('false', false, Mapper::getParameterType(false));

        $scopeDefs = $this->getMetadata()->get(['scopes', $scope]);

        if ($isActive && !empty($scopeDefs['hasActive']) && empty($scopeDefs['isActiveUnavailable'])) {
            $qb->andWhere('rm.is_active=:true OR rr.is_active=:true');
            $qb->setParameter('true', true, Mapper::getParameterType(true));
        }

        $data = $qb->setMaxResults(1)->fetchOne();

        return !empty($data);
    }

}

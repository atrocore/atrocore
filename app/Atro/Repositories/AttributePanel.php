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
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class AttributePanel extends ReferenceData
{
    public function findRelated(Entity $entity, string $link, array $selectParams): EntityCollection
    {
        if ($link === 'attributes') {
            return $this->getEntityManager()->getRepository('Attribute')
                ->where(['attributePanelId' => $entity->get('id')])
                ->order('sortOrder', 'ASC')
                ->find();
        }

        return parent::findRelated($entity, $link, $selectParams);
    }

    public function countRelated(Entity $entity, string $relationName, array $params = []): int
    {
        if ($relationName === 'attributes') {
            return $this->getEntityManager()->getRepository('Attribute')
                ->where(['attributePanelId' => $entity->get('id')])
                ->count();
        }

        return parent::countRelated($entity, $relationName, $params);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('default') && !empty($entity->get('default'))) {
            foreach ($this->find() as $foreign) {
                if ($foreign->get('id') !== $entity->get('id')) {
                    $foreign->set('default', false);
                    $this->save($foreign, ['skipClearCache' => true]);
                }
            }
        }

        if (empty($options['skipClearCache'])) {
            $this->clearCache();
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $attribute = $this->getEntityManager()->getRepository('Attribute')
            ->where([
                'attributePanelId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($attribute)) {
            throw new BadRequest(
                $this->getInjection('language')->translate('entityIsUsed', 'exceptions', 'AttributePanel')
            );
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->clearCache();
    }

    protected function getAllItems(array $params = []): array
    {
        $res = parent::getAllItems($params);

        $entityName = null;
        foreach ($params['whereClause'] ?? [] as $item) {
            if (!empty($item['entityId='])) {
                $entityName = $item['entityId='];
            } elseif (!empty($item['entityId'])) {
                $entityName = $item['entityId'];
            }
        }

        if (!empty($entityName)) {
            foreach ($res as $code => $item) {
                if ($item['entityId'] !== $entityName) {
                    unset($res[$code]);
                }
            }
        }

        return $res;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
        $this->addDependency('language');
    }

    protected function clearCache(): void
    {
        $this->getConfig()->clearReferenceDataCache();
        $this->getInjection('dataManager')->clearCache();
    }
}

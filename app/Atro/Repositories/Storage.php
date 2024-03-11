<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Storage extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('isDefault') && !$entity->get('isDefault')) {
            if (empty($this->where(['isDefault' => true, 'id!=' => $entity->get('id')])->findOne())) {
                throw new BadRequest($this->translate('onlyOneDefaultStorageAllowed', 'exceptions', 'Storage'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('isDefault') && $entity->get('isDefault')) {
            $collection = $this
                ->select(['id'])
                ->where([
                    'isDefault' => true,
                    'id!='      => $entity->get('id')
                ])
                ->find();
            foreach ($collection as $e) {
                $e->set('isDefault', false);
                $this->getEntityManager()->saveEntity($e);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('isDefault')) {
            throw new BadRequest('Default storage can not be deleted.');
        }

        $e = $this->getEntityManager()->getRepository('File')
            ->select(['id'])
            ->where([
                'storageId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($e)) {
            throw new BadRequest($this->translate('storageWithFilesCannotBeRemoved', 'exceptions', 'Storage'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function translate(string $key, string $category, string $scope): string
    {
        return $this->getInjection('language')->translate($key, $category, $scope);
    }
}

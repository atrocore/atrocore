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

namespace Atro\Services;

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class RoleScopeAttribute extends Base
{
    protected $mandatorySelectAttributeList = ["roleScopeId", "roleScopeName", "attributeId"];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if (class_exists("\\Pim\\Module")) {
            $attributesById = [];
            foreach ($collection as $entity) {
                $attributesById[$entity->get('attributeId')] = $entity;
            }

            $attributes = $this->getEntityManager()->getRepository('Attribute')
                ->select(['id', 'name', 'channelId', 'channelName'])
                ->where(['id' => array_keys($attributesById), 'channelId!=' => null])
                ->find();

            foreach ($attributes as $attribute) {
                if (!empty($attributesById[$attribute->get('id')])) {
                    $attributesById[$attribute->get('id')]->set('attributeName', $attribute->get('name') . ' / ' . $attribute->get('channelName'));
                }
            }

        }

    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_fromCollection)) {
            if (class_exists("\\Pim\\Module")) {
                $attribute = $this->getEntityManager()->getRepository('Attribute')
                    ->select(['id', 'name', 'channelId', 'channelName'])
                    ->where(['id' => $entity->get('attributeId'), 'channelId!=' => null])
                    ->findOne();

                if (!empty($attribute)) {
                    $entity->set('attributeName', $attribute->get('name') . ' / ' . $attribute->get('channelName'));
                }
            }
        }
    }
}

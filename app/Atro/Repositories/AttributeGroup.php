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

use Atro\Core\Templates\Repositories\Base;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class AttributeGroup extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        $attribute = $this->getEntityManager()->getRepository('Attribute')
            ->where([
                'attributeGroupId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($attribute)) {
            throw new BadRequest(
                $this->getInjection('language')->translate('entityIsUsed', 'exceptions', 'AttributeGroup')
            );
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}

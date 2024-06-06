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
use Espo\ORM\Entity;

class ExtensibleEnum extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('multilingual') && empty($entity->get('multilingual'))) {
            $this->clearLingualOptions($entity);
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateBeforeRemove($entity);

        parent::beforeRemove($entity, $options);
    }

    public function validateBeforeRemove(Entity $entity): void
    {
        foreach ($this->getMetadata()->get(['entityDefs']) as $entityName => $entityDefs) {
            if (empty($entityDefs['fields'])) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDef) {
                if (empty($fieldDef['notStorable']) && !empty($fieldDef['extensibleEnumId']) && $fieldDef['extensibleEnumId'] === $entity->get('id')) {
                    throw new BadRequest(
                        sprintf(
                            $this->getLanguage()->translate('extensibleEnumIsUsed', 'exceptions', 'ExtensibleEnum'),
                            $entity->get('name'),
                            $this->getLanguage()->translate($field, 'fields', $entity->getEntityType()),
                            $entityName
                        )
                    );
                }
            }
        }
    }


    public function clearLingualOptions(Entity $entity): void
    {
        $fields = $this->getEntityManager()->getRepository('ExtensibleEnumOption')->getLingualFields();
        if (empty($fields)) {
            return;
        }

        foreach ($entity->get('extensibleEnumOptions') as $option) {
            foreach ($fields as $field) {
                $option->set($field, null);
            }
            $this->getEntityManager()->saveEntity($option);
        }
    }
}

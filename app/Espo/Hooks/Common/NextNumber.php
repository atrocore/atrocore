<?php

namespace Espo\Hooks\Common;

use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

class NextNumber extends \Espo\Core\Hooks\Base
{
    public static $order = 9;

    protected function init()
    {
        $this->addDependency('metadata');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function composeNumberAttribute(Entity $nextNumber)
    {
        $entityType = $nextNumber->get('entityType');
        $fieldName = $nextNumber->get('fieldName');
        $value = $nextNumber->get('value');

        $prefix = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $fieldName, 'prefix'], '');
        $padLength = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $fieldName, 'padLength'], 0);

        return $prefix . str_pad(strval($value), $padLength, '0', \STR_PAD_LEFT);
    }

    public function beforeSave(Entity $entity, array $options = array())
    {
        $fieldDefs = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], array());

        foreach ($fieldDefs as $fieldName => $defs) {
            if (isset($defs['type']) && $defs['type'] === 'number') {
                if (!empty($options['import'])) {
                    if ($entity->has($fieldName)) {
                        continue;
                    }
                }
                if (!$entity->isNew()) {
                    if ($entity->isAttributeChanged($fieldName)) {
                        $entity->set($fieldName, $entity->getFetched($fieldName));
                    }
                    continue;
                }
                $this->getEntityManager()->getPdo()->query('LOCK TABLES `next_number` WRITE');
                $nextNumber = $this->getEntityManager()->getRepository('NextNumber')->where(array(
                    'fieldName' => $fieldName,
                    'entityType' => $entity->getEntityType()
                ))->findOne();
                if (!$nextNumber) {
                    $nextNumber = $this->getEntityManager()->getEntity('NextNumber');
                    $nextNumber->set('entityType', $entity->getEntityType());
                    $nextNumber->set('fieldName', $fieldName);
                }
                $entity->set($fieldName, $this->composeNumberAttribute($nextNumber));

                $value = $nextNumber->get('value');
                if (!$value) {
                    $value = 1;
                }
                $value++;

                $nextNumber->set('value', $value);
                $this->getEntityManager()->saveEntity($nextNumber);

                $this->getEntityManager()->getPdo()->query('UNLOCK TABLES');
            }
        }
    }

}


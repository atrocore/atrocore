<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;

class ArrayValue extends \Espo\Core\ORM\Repositories\RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;

    public function storeEntityAttribute(Entity $entity, $attribute, $populateMode = false)
    {
        if (!$entity->getAttributeType($attribute) === Entity::JSON_ARRAY) {
            throw new Error("ArrayValue: Can't store non array attribute.");
        }
        if ($entity->getAttributeType('notStorable')) return;
        if (!$entity->getAttributeParam($attribute, 'storeArrayValues')) return;
        if (!$entity->has($attribute)) return;

        $valueList = $entity->get($attribute);

        if (is_null($valueList)) {
            $valueList = [];
        }

        if (is_string($valueList)) {
            $valueList = json_decode($valueList, true);
        }

        if (!is_array($valueList)) throw new Error("ArrayValue: Bad value passed to JSON_ARRAY attribute {$attribute}.");

        $valueList = array_unique($valueList);

        $toSkipValueList = [];

        if (!$entity->isNew() && !$populateMode) {
            $existingList = $this->where([
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->id,
                'attribute' => $attribute
            ])->find();

            foreach ($existingList as $existing) {
                if (!in_array($existing->get('value'), $valueList)) {
                    $this->deleteFromDb($existing->id);
                } else {
                    $toSkipValueList[] = $existing->get('value');
                }
            }
        }

        foreach ($valueList as $value) {
            if (in_array($value, $toSkipValueList)) continue;
            if (!is_string($value)) continue;

            $arrayValue = $this->get();
            $arrayValue->set([
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->id,
                'attribute' => $attribute,
                'value' => $value
            ]);
            $this->save($arrayValue);
        }
    }

    public function deleteEntityAttribute(Entity $entity, $attribute)
    {
        if (!$entity->id) {
            throw new Error("ArrayValue: Can't delete {$attribute} w/o id given.");
        }
        $list = $this->select(['id'])->where([
            'entityType' => $entity->getEntityType(),
            'entityId' => $entity->id,
            'attribute' => $attribute
        ])->find();

        foreach ($list as $arrayValue) {
            $this->deleteFromDb($arrayValue->id);
        }
    }
}

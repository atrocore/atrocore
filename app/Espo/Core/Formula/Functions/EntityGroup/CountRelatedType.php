<?php

namespace Espo\Core\Formula\Functions\EntityGroup;

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class CountRelatedType extends \Espo\Core\Formula\Functions\Base
{
    protected function init()
    {
        $this->addDependency('entityManager');
        $this->addDependency('selectManagerFactory');
    }

    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        if (count($item->value) < 1) {
            throw new Error();
        }

        $link = $this->evaluate($item->value[0]);

        if (empty($link)) {
            throw new Error("No link passed to countRelated function.");
        }

        $filter = null;
        if (count($item->value) > 1) {
            $filter = $this->evaluate($item->value[1]);
        }

        $entity = $this->getEntity();

        $entityManager = $this->getInjection('entityManager');

        $foreignEntityType = $entity->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new Error();
        }

        $foreignSelectManager = $this->getInjection('selectManagerFactory')->create($foreignEntityType);

        $selectParams = $foreignSelectManager->getEmptySelectParams();

        if ($filter) {
            $foreignSelectManager->applyFilter($filter, $selectParams);
        }

        return $entityManager->getRepository($entity->getEntityType())->countRelated($entity, $link, $selectParams);
    }

}
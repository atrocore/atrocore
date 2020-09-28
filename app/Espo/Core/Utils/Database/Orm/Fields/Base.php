<?php

namespace Espo\Core\Utils\Database\Orm\Fields;
class Base extends \Espo\Core\Utils\Database\Orm\Base
{
    /**
     * Start process Orm converting for fields
     *
     * @param  string $itemName    Field name
     * @param  string $entityName
     * @return array
     */
    public function process($itemName, $entityName)
    {
        $inputs = array(
            'itemName' => $itemName,
            'entityName' => $entityName,
        );
        $this->setMethods($inputs);

        $convertedDefs = $this->load($itemName, $entityName);

        $inputs = $this->setArrayValue(null, $inputs);
        $this->setMethods($inputs);

        return $convertedDefs;
    }
}
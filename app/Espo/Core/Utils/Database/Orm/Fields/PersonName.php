<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

use Espo\Core\Utils\Util;

class PersonName extends Base
{
    protected function load($fieldName, $entityName)
    {
        $subList = array('first' . ucfirst($fieldName), ' ', 'last' . ucfirst($fieldName));

        $tableName = Util::toUnderScore($entityName);

        $orderByField = 'first' . ucfirst($fieldName); // TODO available in settings


        $fullList = array();
        $fullListReverse = array();
        $fieldList = array();
        $like = array();
        $equal = array();

        foreach($subList as $subFieldName) {
            $fieldNameTrimmed = trim($subFieldName);
            if (!empty($fieldNameTrimmed)) {
                $columnName = $tableName . '.' . Util::toUnderScore($fieldNameTrimmed);

                $fullList[] = $fieldList[] = $columnName;
                $like[] = $columnName." LIKE {value}";
                $equal[] = $columnName." = {value}";
            } else {
                $fullList[] = "'" . $subFieldName . "'";
            }
        }

        $fullListReverse = array_reverse($fullList);

        return array(
            $entityName => array (
                'fields' => array(
                    $fieldName => array(
                        'type' => 'varchar',
                        'select' => $this->getSelect($fullList),
                        'where' => array(
                            'LIKE' => "(".implode(" OR ", $like)." OR CONCAT(".implode(", ", $fullList).") LIKE {value} OR CONCAT(".implode(", ", $fullListReverse).") LIKE {value})",
                            '=' => "(".implode(" OR ", $equal)." OR CONCAT(".implode(", ", $fullList).") = {value} OR CONCAT(".implode(", ", $fullListReverse).") = {value})",
                        ),
                        'orderBy' =>  ''. $tableName . '.' . Util::toUnderScore($orderByField) . ' {direction}'
                    ),
                ),
            ),
        );
    }

    protected function getSelect(array $fullList)
    {
        foreach ($fullList as &$item) {

            $rowItem = trim($item, " '");

            if (!empty($rowItem)) {
                $item = "IFNULL(".$item.", '')";
            }
        }

        $select = "TRIM(CONCAT(".implode(", ", $fullList)."))";

        return $select;
    }

}

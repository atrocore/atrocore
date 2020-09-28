<?php

namespace Espo\Core\Utils\Database\Orm\Fields;

use Espo\Core\Utils\Util;

class Currency extends Base
{
    protected function load($fieldName, $entityName)
    {
        $converedFieldName = $fieldName . 'Converted';

        $currencyColumnName = Util::toUnderScore($fieldName);

        $alias = $fieldName . 'CurrencyRate';

        $d = [
            $entityName => [
                'fields' => [
                    $fieldName => [
                        'type' => 'float',
                        'orderBy' => $converedFieldName . ' {direction}'
                    ]
                ]
            ]
        ];

        $params = $this->getFieldParams($fieldName);

        // prepare required
        if (!empty($params['required'])) {
            $d[$entityName]['fields'][$fieldName]['required'] = true;
        }

        if (!empty($params['notStorable'])) {
            $d[$entityName]['fields'][$fieldName]['notStorable'] = true;
        } else {
            $d[$entityName]['fields'][$fieldName . 'Converted'] = [
                'type' => 'float',
                'select' => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate" ,
                'where' =>
                [
                        "=" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate = {value}",
                        ">" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate > {value}",
                        "<" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate < {value}",
                        ">=" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate >= {value}",
                        "<=" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate <= {value}",
                        "<>" => Util::toUnderScore($entityName) . "." . $currencyColumnName . " * {$alias}.rate <> {value}",
                        "IS NULL" => Util::toUnderScore($entityName) . "." . $currencyColumnName . ' IS NULL',
                        "IS NOT NULL" => Util::toUnderScore($entityName) . "." . $currencyColumnName . ' IS NOT NULL'
                ],
                'notStorable' => true,
                'orderBy' => $converedFieldName . " {direction}"
            ];
        }

        return $d;
    }
}

<?php

namespace Espo\Core\Export;

use \Espo\Core\Exceptions\Error;

use \Espo\Core\ORM\Entity;

class Csv extends \Espo\Core\Injectable
{
    protected $dependencyList = [
        'config',
        'preferences',
        'metadata'
    ];

    public function loadAdditionalFields(Entity $entity, $fieldList)
    {
        foreach ($fieldList as $field) {
            if ($this->getInjection('metadata')->get(['entityDefs', $entity->getEntityType(), 'fields', $field, 'type']) === 'linkMultiple') {
                if (!$entity->has($field . 'Ids')) {
                    $entity->loadLinkMultipleField($field);
                }
            }
        }
    }

    public function process($entityType, $params, $dataList)
    {
        if (!is_array($params['attributeList'])) {
            throw new Error();
        }

        $dataList = $this->prepareDataList($dataList);

        $attributeList = $params['attributeList'];

        $delimiter = $this->getInjection('preferences')->get('exportDelimiter');
        if (empty($delimiter)) {
            $delimiter = $this->getInjection('config')->get('exportDelimiter', ';');
        }

        $fp = fopen('php://temp', 'w');
        fputcsv($fp, $attributeList, $delimiter);
        foreach ($dataList as $row) {
            fputcsv($fp, $row, $delimiter);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    protected function prepareDataList($dataList)
    {
        $prepareDataList = [];
        foreach ($dataList as $row) {
            $preparedRow = [];
            foreach ($row as $item) {
                if (is_array($item) || is_object($item)) {
                    $item = \Espo\Core\Utils\Json::encode($item);
                }
                $preparedRow[] = $item;
            }
            $prepareDataList[] = $preparedRow;
        }

        return $prepareDataList;
    }
}
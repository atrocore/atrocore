<?php

declare(strict_types=1);

namespace Treo\Core\Utils\Database\Schema;

/**
 * Class Converter
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Converter extends \Espo\Core\Utils\Database\Schema\Converter
{
    /**
     * @inheritdoc
     */
    protected function getDbFieldParams($fieldParams)
    {
        $dbFieldParams = [];
        foreach ($this->allowedDbFieldParams as $espoName => $dbalName) {
            if (isset($fieldParams[$espoName])) {
                $dbFieldParams[$dbalName] = $fieldParams[$espoName];
            }
        }

        $databaseParams = $this->getConfig()->get('database');
        if (!isset($databaseParams['charset']) || $databaseParams['charset'] == 'utf8mb4') {
            $dbFieldParams['platformOptions'] = [
                'collation' => 'utf8mb4_unicode_ci'
            ];
        }

        switch ($fieldParams['type']) {
            case 'id':
            case 'foreignId':
            case 'foreignType':
                if ($this->getMaxIndexLength() < 3072) {
                    $fieldParams['utf8mb3'] = true;
                }
                break;

            case 'array':
            case 'jsonArray':
            case 'text':
            case 'longtext':
                if (!empty($dbFieldParams['default'])) {
                    $dbFieldParams['comment'] = "default={" . $dbFieldParams['default'] . "}";
                }
                unset($dbFieldParams['default']); //for db type TEXT can't be defined a default value
                break;

            case 'bool':
                $default = false;
                if (array_key_exists('default', $dbFieldParams)) {
                    $default = $dbFieldParams['default'];
                }
                $dbFieldParams['default'] = intval($default);
                break;
        }

        if (isset($fieldParams['autoincrement']) && $fieldParams['autoincrement']) {
            $dbFieldParams['unique'] = true;
            $dbFieldParams['notnull'] = true;
        }

        if (isset($fieldParams['utf8mb3']) && $fieldParams['utf8mb3']) {
            $dbFieldParams['platformOptions'] = ['collation' => 'utf8_unicode_ci'];
        }

        return $dbFieldParams;
    }
}

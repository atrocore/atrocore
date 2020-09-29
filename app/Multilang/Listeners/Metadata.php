<?php

declare(strict_types=1);

namespace Multilang\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;

/**
 * Class Metadata
 *
 * @author r.ratsun@treolabs.com
 */
class Metadata extends AbstractListener
{
    /**
     * Modify
     *
     * @param Event $event
     */
    public function modify(Event $event)
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return false;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return false;
        }

        // get data
        $data = $event->getArgument('data');

        /**
         * Set multi-lang params to few fields
         */
        $fields = ['bool', 'enum', 'multiEnum', 'text', 'varchar', 'wysiwyg'];
        foreach ($fields as $field) {
            $data['fields'][$field]['params'][] = [
                'name'    => 'isMultilang',
                'type'    => 'bool',
                'tooltip' => true
            ];
        }

        /**
         * Set multi-lang fields to entity defs
         */
        foreach ($data['entityDefs'] as $scope => $rows) {
            if (!isset($rows['fields']) || !is_array($rows['fields'])) {
                continue 1;
            }
            foreach ($rows['fields'] as $field => $params) {
                if (!empty($params['isMultilang'])) {
                    foreach ($locales as $locale) {
                        // prepare multi-lang field
                        $mField = $field . ucfirst(Util::toCamelCase(strtolower($locale)));

                        // prepare params
                        $mParams = $params;
                        if (isset($data['entityDefs'][$scope]['fields'][$mField])) {
                            if (in_array($mParams['type'], ['enum', 'multiEnum'])) {
                                $data['entityDefs'][$scope]['fields'][$mField]['options'] = $mParams['options'];
                                if (isset($mParams['optionColors'])) {
                                    $data['entityDefs'][$scope]['fields'][$mField]['optionColors'] = $mParams['optionColors'];
                                }
                            }
                            $mParams = array_merge($mParams, $data['entityDefs'][$scope]['fields'][$mField]);
                        }
                        $mParams['isMultilang'] = false;
                        $mParams['hideMultilang'] = true;
                        $mParams['multilangField'] = $field;
                        $mParams['multilangLocale'] = $locale;
                        $mParams['isCustom'] = false;
                        if (in_array($mParams['type'], ['enum', 'multiEnum'])) {
                            $mParams['layoutMassUpdateDisabled'] = true;
                            $mParams['readOnly'] = true;
                            $mParams['required'] = false;
                        }

                        $data['entityDefs'][$scope]['fields'][$mField] = $mParams;
                    }
                }
            }
        }

        // set data
        $event->setArgument('data', $data);
    }
}

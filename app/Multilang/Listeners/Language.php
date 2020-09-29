<?php

declare(strict_types=1);

namespace Multilang\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Core\Utils\Util;
use Treo\Listeners\AbstractListener;

/**
 * Class Language
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Language extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return false;
        }

        // get languages
        if (empty($languages = $this->getConfig()->get('inputLanguageList', []))) {
            return false;
        }

        // get data
        $data = $event->getArgument('data');

        foreach ($data as $locale => $rows) {
            foreach ($rows as $scope => $items) {
                foreach (['fields', 'tooltips'] as $type) {
                    if (isset($items[$type])) {
                        foreach ($items[$type] as $field => $value) {
                            foreach ($languages as $language) {
                                // prepare multi-lang field
                                $mField = $field . ucfirst(Util::toCamelCase(strtolower($language)));

                                if (!isset($data[$locale][$scope][$type][$mField])) {
                                    if ($type == 'fields') {
                                        $data[$locale][$scope][$type][$mField] = $value . ' › ' . $language;
                                    } else {
                                        $data[$locale][$scope][$type][$mField] = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // set data
        $event->setArgument('data', $data);
    }
}

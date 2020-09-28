<?php

declare(strict_types=1);

namespace Treo\Controllers;

/**
 * Controller Settings
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Settings extends \Espo\Controllers\Settings
{
    /**
     * @inheritdoc
     */
    protected function getConfigData()
    {
        // get config
        $config = parent::getConfigData();

        // prepare tabList
        $config = $this->prepareTabList($config);

        return $config;
    }

    /**
     * Prepare tab list
     *
     * @param array $config
     *
     * @return array
     */
    protected function prepareTabList(array $config): array
    {
        if (!empty($config['tabList'])) {
            $newTabList = [];
            foreach ($config['tabList'] as $item) {
                if (is_string($item) && ($this->getMetadata()->get("scopes.$item.tab") || $item == '_delimiter_')) {
                    $newTabList[] = $item;
                }
            }
            $config['tabList'] = $newTabList;
        }

        return $config;
    }
}

<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Espo\Core\Utils;

use Espo\Core\Container;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Repositories\Locale;

/**
 * Class Config
 */
class Config
{
    public const DEFAULT_LOCALE
        = [
            'language'          => 'en_US',
            'dateFormat'        => 'MM/DD/YYYY',
            'timeZone'          => 'UTC',
            'weekStart'         => 0,
            'timeFormat'        => 'HH:mm',
            'thousandSeparator' => ',',
            'decimalMark'       => '.',
        ];

    /**
     * Path of default config file
     *
     * @access private
     * @var string
     */
    private $defaultConfigPath = CORE_PATH . '/Espo/Core/defaults/config.php';

    private $systemConfigPath = CORE_PATH . '/Espo/Core/defaults/systemConfig.php';

    protected $configPath = 'data/config.php';

    /**
     * Array of admin items
     *
     * @access protected
     * @var array
     */
    protected $adminItems = array();

    protected $associativeArrayAttributeList = [
        'currencyRates',
        'database',
        'logger',
        'defaultPermissions',
    ];


    /**
     * Contains content of config
     *
     * @access private
     * @var array
     */
    private $data;

    private $changedData = array();
    private $removeData = array();

    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var Container
     */
    private $container;

    /**
     * Config constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->fileManager = new FileManager();
        $this->container = $container;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * Get an option from config
     *
     * @param string $name
     * @param string $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if ($name == 'isModulesLoaded') {
            return $this->container->get('moduleManager')->isLoaded();
        }

        if ($name == 'unitsOfMeasure') {
            return $this->getUnitsOfMeasure();
        }

        if (in_array($name, array_merge(['locales'], array_keys(self::DEFAULT_LOCALE)))) {
            return $this->loadLocales()[$name];
        }

        $keys = explode('.', $name);

        $lastBranch = $this->loadConfig();
        foreach ($keys as $keyName) {
            if (isset($lastBranch[$keyName]) && (is_array($lastBranch) || is_object($lastBranch))) {
                if (is_array($lastBranch)) {
                    $lastBranch = $lastBranch[$keyName];
                } else {
                    $lastBranch = $lastBranch->$keyName;
                }
            } else {
                return $default;
            }
        }

        return $lastBranch;
    }

    /**
     * Whether parameter is set
     *
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        $keys = explode('.', $name);

        $lastBranch = $this->loadConfig();
        foreach ($keys as $keyName) {
            if (isset($lastBranch[$keyName]) && (is_array($lastBranch) || is_object($lastBranch))) {
                if (is_array($lastBranch)) {
                    $lastBranch = $lastBranch[$keyName];
                } else {
                    $lastBranch = $lastBranch->$keyName;
                }
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Set an option to the config
     *
     * @param string $name
     * @param string $value
     * @return bool
     */
    public function set($name, $value = null, $dontMarkDirty = false)
    {
        if (is_object($name)) {
            $name = get_object_vars($name);
        }

        if (!is_array($name)) {
            $name = array($name => $value);
        }

        foreach ($name as $key => $value) {
            if (in_array($key, $this->associativeArrayAttributeList) && is_object($value)) {
                $value = (array)$value;
            }
            $this->data[$key] = $value;
            if (!$dontMarkDirty) {
                $this->changedData[$key] = $value;
            }
        }
    }

    /**
     * Remove an option in config
     *
     * @param  string $name
     * @return bool | null - null if an option doesn't exist
     */
    public function remove($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            $this->removeData[] = $name;
            return true;
        }

        return null;
    }

    public function save()
    {
        $data = include($this->configPath);
        if (empty($data) || !is_array($data)) {
            return false;
        }

        $values = $this->changedData;
        $removeData = empty($this->removeData) ? null : $this->removeData;

        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $data[$key] = $value;
            }
        }

        if (is_array($removeData)) {
            foreach ($removeData as $key) {
                unset($data[$key]);
            }
        }

        $content = $this->getFileManager()->wrapForDataExport($data, true);

        if (strpos($content, '<?php') === false) {
            return false;
        }

        $result = file_put_contents($this->configPath, $content, LOCK_EX);

        if ($result) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->configPath);
            }
            $this->changedData = array();
            $this->removeData = array();
            $this->loadConfig(true);
        }

        return $result;
    }

    public function getDefaults()
    {
        return $this->getFileManager()->getPhpContents($this->defaultConfigPath);
    }

    /**
     * Return an Object of all configs
     * @param  boolean $reload
     * @return array()
     */
    protected function loadConfig($reload = false)
    {
        if (!$reload && isset($this->data) && !empty($this->data)) {
            return $this->data;
        }

        $configPath = file_exists($this->configPath) ? $this->configPath : $this->defaultConfigPath;

        $this->data = $this->getFileManager()->getPhpContents($configPath);

        $systemConfig = $this->getFileManager()->getPhpContents($this->systemConfigPath);
        $this->data = Util::merge($systemConfig, $this->data);

        return $this->data;
    }

    protected function loadLocales():array
    {
        $result = self::DEFAULT_LOCALE;

        if (!$this->get('isInstalled', false)) {
            return $result;
        }

        $result['locales'] = $this->getCachedLocales();

        $localeId = $this->get('localeId');
        foreach (self::DEFAULT_LOCALE as $name => $value) {
            $result[$name] = $result['locales'][$localeId][$name];
        }

        return $result;
    }

    public function getCachedLocales(): array
    {
        if (file_exists(Locale::CACHE_FILE)) {
            return Json::decode(file_get_contents(Locale::CACHE_FILE), true);
        }

        $data = $this
            ->container
            ->get('pdo')
            ->query(
                "SELECT l.*, m.id as measure_id, m.name as measure_name, m.data as measure_data FROM `locale` l LEFT JOIN `locale_measure` lm ON lm.locale_id=l.id AND lm.deleted=0 LEFT JOIN measure m ON m.id=lm.measure_id AND m.deleted=0 WHERE l.deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            foreach (self::DEFAULT_LOCALE as $k => $v) {
                $preparedKey = Util::toUnderScore($k);
                $result[$row['id']][$k] = isset($row[$preparedKey]) ? $row[$preparedKey] : $v;
            }
            $result[$row['id']]['name'] = $row['name'];
            $result[$row['id']]['weekStart'] = $result[$row['id']]['weekStart'] === 'monday' ? 1 : 0;
            if (!empty($row['measure_id'])) {
                $measureData = empty($row['measure_data']) ? [] : Json::decode($row['measure_data'], true);
                $result[$row['id']]['measures'][$row['measure_id']] = [
                    'id'    => $row['measure_id'],
                    'name'  => $row['measure_name'],
                    'units' => isset($measureData["locale_{$row['id']}"]) ? $measureData["locale_{$row['id']}"] : [],
                    'defaultUnit' => isset($measureData["locale_{$row['id']}_default"]) ? $measureData["locale_{$row['id']}_default"] : ''
                ];
            }
        }

        foreach ($result as $id => $row) {
            $result[$id]['measures'] = empty($row['measures']) ? [] : array_values($row['measures']);
        }

        if (!empty($result)) {
            file_put_contents(Locale::CACHE_FILE, Json::encode($result));
        }

        return $result;
    }


    /**
     * Get config acording to restrictions for a user
     *
     * @param $isAdmin
     *
     * @return array
     */
    public function getData($isAdmin = null)
    {
        $data = array_merge($this->loadConfig(), $this->loadLocales());
        $data['unitsOfMeasure'] = $this->getUnitsOfMeasure();

        $restrictedConfig  = $data;
        foreach($this->getRestrictItems($isAdmin) as $name) {
            if (isset($restrictedConfig[$name])) {
                unset($restrictedConfig[$name]);
            }
        }

        return $restrictedConfig;
    }


    /**
     * Set JSON data acording to restrictions for a user
     *
     * @param $isAdmin
     * @return bool
     */
    public function setData($data, $isAdmin = null)
    {
        $restrictItems = $this->getRestrictItems($isAdmin);

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        $values = array();
        foreach ($data as $key => $item) {
            if (!in_array($key, $restrictItems)) {
                $values[$key] = $item;
            }
        }

        return $this->set($values);
    }

    /**
     * Get admin items
     *
     * @return object
     */
    protected function getRestrictItems($onlySystemItems = null)
    {
        $data = $this->loadConfig();

        if ($onlySystemItems) {
            return $data['systemItems'];
        }

        if (empty($this->adminItems)) {
            $this->adminItems = array_merge($data['systemItems'], $data['adminItems']);
        }

        if ($onlySystemItems === false) {
            return $this->adminItems;
        }

        return array_merge($this->adminItems, $data['userItems']);
    }


    /**
     * Check if an item is allowed to get and save
     *
     * @param $name
     * @param $isAdmin
     * @return bool
     */
    protected function isAllowed($name, $isAdmin = false)
    {
        if (in_array($name, $this->getRestrictItems($isAdmin))) {
            return false;
        }

        return true;
    }

    public function getSiteUrl()
    {
        return rtrim($this->get('siteUrl'), '/');
    }

    protected function getUnitsOfMeasure()
    {
        if (!$this->get('isInstalled', false) || !$this->container->get('user') || !$this->container->get('user')->isFetched()) {
            return new \stdClass();
        }

        return $this->container->get('serviceFactory')->create('Measure')->getUnitsOfMeasure();
    }
}

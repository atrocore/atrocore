<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

namespace Espo\Core\Utils;

use Atro\Core\Container;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types as FieldTypes;
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
            'thousandSeparator' => '',
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

    protected $customStylesheetDir = 'css/treo/';

    protected $customHeadCodeDir = 'code/atro/';

    protected $customHeadCodeFilename = 'atro-head-code.html';

    protected $customStyleFields = [
        'navigationManuBackgroundColor',
        'navigationMenuFontColor',
        'linkFontColor',
        'primaryColor',
        'secondaryColor',
        'primaryFontColor',
        'secondaryFontColor',
        'labelColor',
        'anchorNavigationBackground',
        'iconColor',
        'primaryBorderColor',
        'secondaryBorderColor',
        'panelTitleColor',
        'headerTitleColor',
        'success',
        'notice',
        'information',
        'error'
    ];

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
        $this->container = $container->get('container');
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

        if (in_array($name, array_merge(['locales'], array_keys(self::DEFAULT_LOCALE)))) {
            $res = $this->loadLocales();
            return $res[$name] ?? null;
        }

        if ($name == 'interfaceLocales') {
            $res = $this->loadInterfaceLocales();
            return $res[$name] ?? null;
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
        if (!file_exists($this->configPath)) {
            return false;
        }

        $data = include($this->configPath);

        if (empty($data) || !is_array($data)) {
            return false;
        }

        $values = $this->changedData;
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $data[$key] = $value;
            }
        }

        $removeData = empty($this->removeData) ? [] : $this->removeData;
        if (is_array($removeData)) {
            $removeData[] = '_prev';
            $removeData[] = '_silentMode';

            foreach ($removeData as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key]);
                }
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

    protected function loadLocales(): array
    {
        $result = self::DEFAULT_LOCALE;

        if (!$this->get('isInstalled', false)) {
            return $result;
        }

        $result['locales'] = $this->getCachedLocales();

        $localeId = $this->get('localeId');
        foreach (self::DEFAULT_LOCALE as $name => $value) {
            if (isset($result['locales'][$localeId][$name])) {
                $result[$name] = $result['locales'][$localeId][$name];
            }
        }

        return $result;
    }

    protected function loadInterfaceLocales(): array
    {
        $locales = [$this->get('mainLanguage', 'en_US')];
        if (!empty($this->get('locales', []))) {
            $locales = array_merge($locales, array_column($this->get('locales', []), 'language'));
        }

        if (!empty($this->get('isMultilangActive'))) {
            $locales = array_merge($locales, $this->get('inputLanguageList', []));
        }

        return ['interfaceLocales' => array_values(array_unique($locales))];
    }

    public function getCachedLocales(): array
    {
        $data = $this->container->get('dataManager')->getCacheData('locales');
        if (is_array($data)) {
            return $data;
        }

        /** @var Connection $connection */
        $connection = $this->container->get('connection');

        $qb = $connection->createQueryBuilder();
        $data = $qb
            ->select('l.*')
            ->from($connection->quoteIdentifier('locale'), 'l')
            ->where('l.deleted = :deleted')
            ->setParameter('deleted', false, FieldTypes::BOOLEAN)
            ->fetchAllAssociative();

        $result = [];
        foreach ($data as $row) {
            foreach (self::DEFAULT_LOCALE as $k => $v) {
                $preparedKey = Util::toUnderScore($k);
                $result[$row['id']][$k] = isset($row[$preparedKey]) ? $row[$preparedKey] : $v;
            }
            $result[$row['id']]['name'] = $row['name'];
            $result[$row['id']]['weekStart'] = $result[$row['id']]['weekStart'] === 'monday' ? 1 : 0;
        }

        $this->container->get('dataManager')->setCacheData('locales', $result);

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
        $data = array_merge($this->loadConfig(), $this->loadLocales(), $this->loadInterfaceLocales());

        $data = $this->prepareStylesheetConfigForOutput($data);
        $data = $this->prepareCustomHeadCodeForOutput($data);

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

        $values = $this->prepareStylesheetConfigForSave($values);
        $values = $this->prepareCustomHeadCodeForSave($values);

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

    public function getSiteUrl()
    {
        return rtrim($this->get('siteUrl'), '/');
    }

    public function getCustomHeadCode(): ?string
    {
        $path = $this->getCustomHeadCodePath();

        if (!empty($path) && file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }

    protected function prepareStylesheetConfigForOutput(array $data): array
    {
        $theme = $this->get('theme');
        $customStylesheetsList = $this->get('customStylesheetsList', []);

        if (isset($customStylesheetsList[$theme]) && !empty($themeData = $customStylesheetsList[$theme])) {
            if (!empty($themeData['customStylesheetPath']) && file_exists($themeData['customStylesheetPath'])) {
                $data['customStylesheetPath'] = $themeData['customStylesheetPath'];
                $data['customStylesheet'] = file_get_contents($themeData['customStylesheetPath']);
            }
        } else {
            $themeData = [];
        }

        foreach ($this->customStyleFields as $item) {
            if (isset($themeData[$item])) {
                $data[$item] = $themeData[$item];
            }
        }

        return $data;
    }

    protected function prepareCustomHeadCodeForOutput(array $data): array
    {
        $data['customHeadCode'] = $this->getCustomHeadCode();

        return $data;
    }

    protected function prepareStylesheetConfigForSave(array $data): array
    {
        $currTheme = $this->get('theme');
        $currData = $this->get('customStylesheetsList', []);

        // create custom css theme file
        if (isset($data['customStylesheet'])) {
            Util::createDir($this->customStylesheetDir);
            file_put_contents($this->getCustomStylesheetPath(), $data['customStylesheet']);

            $currData[$currTheme]['customStylesheetPath'] = $this->getCustomStylesheetPath();
        }
        unset($data['customStylesheet']);

        // prepare theme custom data
        foreach ($this->customStyleFields as $field) {
            if (!empty($data[$field])) {
                $currData[$currTheme][$field] = $data[$field];
            } elseif (isset($data[$field]) && isset($currData[$currTheme]) && isset($currData[$currTheme][$field])) {
                unset($currData[$currTheme][$field]);
            }

            unset($data[$field]);
        }

        $data['customStylesheetsList'] = $currData;

        return $data;
    }

    protected function prepareCustomHeadCodeForSave(array $data): array
    {
        // create custom head scripts file
        if (isset($data['customHeadCode'])) {
            Util::createDir($this->customHeadCodeDir);

            $path = $this->getCustomHeadCodePath();

            file_put_contents($path, $data['customHeadCode']);
            $data['customHeadCodePath'] = $path;
        }
        unset($data['customHeadCode']);

        return $data;
    }

    protected function getCustomStylesheetFilename(): ?string
    {
        return $this->getMetadata()->get(['themes', $this->get('theme'), 'customStylesheetName']);
    }

    protected function getCustomStylesheetPath(): string
    {
        return $this->customStylesheetDir . $this->getCustomStylesheetFilename();
    }

    protected function getCustomHeadCodePath(): string
    {
        return $this->customHeadCodeDir . $this->customHeadCodeFilename;
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }
}

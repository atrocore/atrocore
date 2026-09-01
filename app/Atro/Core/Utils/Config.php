<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Repositories\SoftwarePackage as SoftwarePackageRepository;
use Espo\Core\Utils\File\Manager as FileManager;

final class Config
{
    /**
     * System config defaults, merged under data/config.php on load
     */
    protected array $systemConfig
        = [
            'defaultPermissions' => [
                'dir'   => '0775',
                'file'  => '0664',
                'user'  => '',
                'group' => '',
            ],
            'systemItems'        => [
                'systemItems',
                'adminItems',
                'configPath',
                'cachePath',
                'database',
                'logger',
                'isInstalled',
                'defaultPermissions',
                'passwordSalt',
                'cryptKey',
                'userLimit',
                'stylesheet',
                'userItems',
                'phpBinPath',
            ],
            'adminItems'         => [
                'devMode',
                'adminPanelIframeUrl',
                'authTokenLifetime',
                'authTokenMaxIdleTime',
                'leadCaptureAllowOrigin',
                // secrets: must stay readable/writable for admins via Settings UI,
                // but hidden from non-admin API responses and script (Twig) contexts
                'smtpPassword',
                'oidcClientSecret',
                'gitlabApiToken',
                'oktaApiToken',
                'etimClientSecret',
                'icecatPassword',
            ],
            'userItems'          => [
                'outboundEmailFromAddress',
                'integrations',
            ],
            'isInstalled'        => false,
        ];

    protected ?array $referenceData = null;

    /**
     * Path of default config file
     *
     * @access private
     * @var string
     */
    private $defaultConfigPath = VENDOR_PATH . '/atrocore-legacy/app/Espo/Core/defaults/config.php';

    protected $configPath = 'data/config.php';

    protected string $customHeadCodeDir = 'public/client/custom/html';

    protected string $customHeadCodeFilename = 'head-code.html';

    protected string $customStylesheetDir = 'public/client/custom/css';

    protected string $customStylesheetFileName = 'custom-css.css';

    /**
     * Array of admin items
     *
     * @access protected
     * @var array
     */
    protected $adminItems = array();

    protected $associativeArrayAttributeList
        = [
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
    protected $data;

    protected $changedData = array();
    protected $removeData = array();

    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @var Container
     */
    protected $container;

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

    public function clearReferenceDataCache(): void
    {
        $this->referenceData = null;
    }

    protected function loadConfig(bool $reload = false): array
    {
        if ($reload || empty($this->data)) {
            $configPath = file_exists($this->configPath) ? $this->configPath : $this->defaultConfigPath;
            $this->data = Util::merge($this->systemConfig, $this->getFileManager()->getPhpContents($configPath));
        }

        // put reference data into config
        $this->putReferenceDataIntoConfig();

        $minimumStability = SoftwarePackageRepository::getComposerData()['minimum-stability'] ?? 'stable';

        $this->data['onlyStableReleases'] = $minimumStability === 'stable';

        return $this->data;
    }

    public function getData($isAdmin = null)
    {
        $data = $this->loadConfig();
        $data = $this->prepareCustomHeadCodeForOutput($data);
        $data = $this->prepareStylesheetConfigForOutput($data);
        $restrictedConfig = $data;

        foreach ($this->getRestrictItems($isAdmin) as $name) {
            if (isset($restrictedConfig[$name])) {
                unset($restrictedConfig[$name]);
            }
        }

        foreach (['Translation', 'UiHandler'] as $entityName) {
            if (isset($restrictedConfig['referenceData'][$entityName])) {
                unset($restrictedConfig['referenceData'][$entityName]);
            }
        }

        if (isset($restrictedConfig['clickhouse']['database'])) {
            unset($restrictedConfig['clickhouse']['database']);
        }

        return $restrictedConfig;
    }

    protected function putReferenceDataIntoConfig(): void
    {
        $this->data['referenceData'] = [];
        $this->data['inputLanguageList'] = [];

        foreach ($this->getReferenceData() as $entityName => $items) {
            $this->data['referenceData'][$entityName] = $items;

            switch ($entityName) {
                case 'Locale':
                    foreach ($items as $row) {
                        if (!empty($row['id'])) {
                            $this->data['locales'][$row['id']] = [
                                'code'                           => $row['code'],
                                'name'                           => $row['name'] ?? 'en_US',
                                'language'                       => $row['languageCode'] ?? 'en_US',
                                'fallbackLanguage'               => $row['fallbackLanguageCode'] ?? null,
                                'weekStart'                      => $row['weekStart'] === 'monday' ? 1 : 0,
                                'dateFormat'                     => $row['dateFormat'] ?? 'MM/DD/YYYY',
                                'timeFormat'                     => $row['timeFormat'] ?? 'HH:mm',
                                'timeZone'                       => $row['timeZone'] ?? 'UTC',
                                'thousandSeparator'              => $row['thousandSeparator'] ?? '',
                                'decimalMark'                    => $row['decimalMark'] ?? '.',
                                'displayLabelsInContentLanguage' => $row['displayLabelsInContentLanguage'] ?? false,
                                'disableForUi'                   => $row['disableForUi'] ?? false,
                            ];
                        }

                    }
                    break;
                case 'Language':
                    foreach ($items as $row) {
                        if (!empty($row['role'])) {
                            if ($row['role'] === 'main') {
                                $this->data['mainLanguage'] = $row['code'];
                            } elseif ($row['role'] === 'additional') {
                                $this->data['inputLanguageList'][] = $row['code'];
                            }
                        }
                    }
                    break;
            }
        }

        $this->data['isMultilangActive'] = !empty($this->data['inputLanguageList']);
    }

    protected function getReferenceData(): array
    {
        if ($this->referenceData !== null) {
            return $this->referenceData;
        }

        $this->referenceData = [];

        if (is_dir(ReferenceData::DIR_PATH)) {
            foreach (scandir(ReferenceData::DIR_PATH) as $file) {
                if (!is_file(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }
                $entityName = str_replace('.json', '', $file);
                $items = @json_decode(file_get_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . $file), true);
                if (!empty($items)) {
                    $this->referenceData[$entityName] = $items;
                }
            }
        }

        return $this->referenceData;
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
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if ($name == 'isModulesLoaded') {
            return $this->container->get('moduleManager')->isLoaded();
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
     *
     * @return bool
     */
    public function has($name)
    {
        $keys = explode('.', $name);

        $lastBranch = $this->loadConfig();
        foreach ($keys as $keyName) {
            if ((is_array($lastBranch) && array_key_exists($keyName, $lastBranch)) || (is_object($lastBranch) && property_exists($lastBranch, $keyName))) {
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
     *
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
     * @param string $name
     *
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
     * Set JSON data acording to restrictions for a user
     *
     * @param $isAdmin
     *
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

        $values = $this->prepareCustomHeadCodeForSave($values);

        $values = $this->prepareStylesheetConfigForSave($values);

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
        if (!empty($data['customStylesheetPath']) && file_exists($data['customStylesheetPath'])) {
            $data['customStylesheet'] = file_get_contents($data['customStylesheetPath']);
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
        // create custom css theme file
        if (array_key_exists('customStylesheet', $data)) {
            if (empty($data['customStylesheet']) && !empty($this->get('customStylesheetPath')) && file_exists($this->get('customStylesheetPath'))) {
                unlink($this->get('customStylesheetPath'));
                $data['customStylesheetPath'] = null;
            } else {
                if (!empty($data['customStylesheet'])) {
                    Util::createDir($this->customStylesheetDir);
                    file_put_contents($this->getCustomStylesheetPath(), $data['customStylesheet']);

                    $data['customStylesheetPath'] = $this->getCustomStylesheetPath();
                }
            }
        }
        unset($data['customStylesheet']);

        return $data;
    }

    protected function prepareCustomHeadCodeForSave(array $data): array
    {
        // create custom head scripts file
        if (array_key_exists('customHeadCode', $data)) {
            if (empty($data['customHeadCode']) && !empty($this->get('customHeadCodePath')) && file_exists($this->get('customHeadCodePath'))) {
                unlink($this->get('customHeadCodePath'));
                $data['customHeadCodePath'] = null;
            } else {
                if (!empty($data['customHeadCode'])) {
                    Util::createDir($this->customHeadCodeDir);

                    $path = $this->getCustomHeadCodePath();

                    file_put_contents($path, $data['customHeadCode']);
                    $data['customHeadCodePath'] = $path;
                }
            }
        }
        unset($data['customHeadCode']);

        return $data;
    }

    protected function getCustomHeadCodePath(): string
    {
        return $this->customHeadCodeDir . '/' . $this->customHeadCodeFilename;
    }

    protected function getCustomStylesheetPath(): string
    {
        return $this->customStylesheetDir . '/' . $this->customStylesheetFileName;
    }
}
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

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Repositories\SoftwarePackage as SoftwarePackageRepository;

final class Config
{
    private const string CONFIG_PATH = 'data/config.php';

    /**
     * System config defaults, merged under data/config.php on load
     */
    private array $systemConfig
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

    private ?array $referenceData = null;

    private string $customHeadCodeDir = 'public/client/custom/html';

    private string $customHeadCodeFilename = 'head-code.html';

    private string $customStylesheetDir = 'public/client/custom/css';

    private string $customStylesheetFileName = 'custom-css.css';

    private array $adminItems = array();

    private array $associativeArrayAttributeList
        = [
            'currencyRates',
            'database',
            'logger',
            'defaultPermissions',
        ];

    private array $data = [];
    private array $changedData = [];
    private array $removeData = [];

    /**
     * Config data as stored on disk. Falls back to the defaults before the
     * installation has created the file - that is a normal state, unlike a
     * file that exists but does not hold an array.
     */
    public static function load(): array
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return self::getDefaults();
        }

        $data = include self::CONFIG_PATH;

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Config file %s is corrupted.', self::CONFIG_PATH));
        }

        return $data;
    }

    /**
     * Creates the config file from the defaults on a fresh installation.
     * Returns false when the file is already there, or could not be written.
     */
    public static function createIfMissing(): bool
    {
        if (file_exists(self::CONFIG_PATH)) {
            return false;
        }

        $data = self::getDefaults();
        $data['passwordSalt'] = bin2hex(random_bytes(16));

        return self::writeAtomically(self::exportPhp($data));
    }

    public static function isInstalled(): bool
    {
        return !empty(self::load()['isInstalled']);
    }

    public function getSiteUrl(): string
    {
        return rtrim($this->get('siteUrl'), '/');
    }

    public function clearReferenceDataCache(): void
    {
        $this->referenceData = null;
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

        if (isset($restrictedConfig['clickhouse']['database'])) {
            unset($restrictedConfig['clickhouse']['database']);
        }

        return $restrictedConfig;
    }

    public function get(string $name, mixed $default = null): mixed
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
                return $default;
            }
        }

        return $lastBranch;
    }

    public function has(string $name): bool
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

    public function set(string $name, mixed $value = null): void
    {
        // a bare set() must not leave $data holding only that one key
        $this->loadConfig();

        // stdClass from the REST payload would end up as \stdClass::__set_state() in config.php
        if (is_object($value) && in_array($name, $this->associativeArrayAttributeList, true)) {
            $value = (array)$value;
        }

        $this->data[$name] = $value;
        $this->changedData[$name] = $value;
    }

    public function remove(string $name): bool
    {
        $this->loadConfig();

        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            $this->removeData[] = $name;
            return true;
        }

        return false;
    }

    public function save(): bool
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return false;
        }

        $data = self::load();

        if (empty($data) || !is_array($data)) {
            return false;
        }

        // change config data
        foreach ($this->changedData as $key => $value) {
            $data[$key] = $value;
        }

        // remove config data
        foreach ($this->removeData as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        if (!self::writeAtomically(self::exportPhp($data))) {
            return false;
        }

        $this->changedData = [];
        $this->removeData = [];
        $this->loadConfig(true);

        return true;
    }

    /**
     * Apply incoming data, dropping whatever the given role is not allowed to write.
     */
    public function setData(array|\stdClass $data, ?bool $isAdmin = null): void
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

        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getCustomHeadCode(): ?string
    {
        $path = $this->getCustomHeadCodePath();

        if (!empty($path) && file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }

    protected function loadConfig(bool $reload = false): array
    {
        if ($reload || empty($this->data)) {
            $this->data = self::load();
            $this->data = Util::merge($this->systemConfig, $this->data);
        }

        // put reference data into config
        $this->putReferenceDataIntoConfig();

        $minimumStability = SoftwarePackageRepository::getComposerData()['minimum-stability'] ?? 'stable';

        $this->data['onlyStableReleases'] = $minimumStability === 'stable';

        return $this->data;
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

    /**
     * Renders config data as a loadable PHP file: short array syntax,
     * 4-space indentation, stdClass as `(object) [...]`.
     */
    private static function exportPhp(array $data): string
    {
        return "<?php\nreturn " . self::exportValue($data) . ";\n";
    }

    private static function exportValue(mixed $value, int $level = 0): string
    {
        if ($value instanceof \stdClass) {
            return '(object) ' . self::exportValue(get_object_vars($value), $level);
        }

        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $level + 1);

        $rows = [];
        foreach ($value as $key => $item) {
            $rows[] = $indent . var_export($key, true) . ' => ' . self::exportValue($item, $level + 1);
        }

        return "[\n" . implode(",\n", $rows) . "\n" . str_repeat('    ', $level) . ']';
    }

    /**
     * Writes through a temporary file and renames it into place, so a concurrent
     * reader never includes a half-written config.
     */
    private static function writeAtomically(string $content): bool
    {
        $path = self::CONFIG_PATH;

        $tmp = $path . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, $content) === false) {
            return false;
        }

        // a fresh file gets umask permissions - keep whatever the config had
        if (file_exists($path) && ($perms = fileperms($path)) !== false) {
            chmod($tmp, $perms & 0777);
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        return true;
    }

    private static function getDefaults(): array
    {
        return [
            'isInstalled'                     => false,
            'passwordSalt'                    => 'some-salt',
            'amountOfDbDumps'                 => 14,
            'database'                        => [
                'driver'   => 'pdo_mysql',
                'host'     => 'localhost',
                'port'     => '',
                'charset'  => 'utf8mb4',
                'dbname'   => '',
                'user'     => '',
                'password' => ''
            ],
            'maxConcurrentWorkers'            => 6,
            'currencyRates'                   => [],
            'outboundEmailFromAddress'        => '',
            'logger'                          => [
                'path'          => 'data/logs/atro.log',
                'level'         => 'WARNING', /** DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY */
                'rotation'      => true,
                'maxFileNumber' => 7,
            ],
            'assignmentEmailNotifications'    => false,
            'disabledCountQueryEntityList'    => [],
            'authTokenLifetime'               => 0,
            'authTokenMaxIdleTime'            => 120,
            'userNameRegularExpression'       => '[^a-z0-9\-@_\.\s]',
            'displayListViewRecordCount'      => true,
            'aclStrictMode'                   => false,
            'textFilterUseContainsForVarchar' => false,
            'noteDeleteThresholdPeriod'       => '1 month',
            'noteEditThresholdPeriod'         => '7 days',
            'recordsPerPage'                  => 50,
            'recordsPerPageSmall'             => 20,
            'lastViewedCount'                 => 20,
            'useCache'                        => true,
            'applicationName'                 => 'AtroPIM',
            'filesPath'                       => 'upload/files/',
            'thumbnailsPath'                  => 'upload/thumbnails/',
            'chunkFileSize'                   => 2,
            'fileUploadStreamCount'           => 3,
            'globalSearchEntityList'          => ['File', 'Folder', 'Attribute', 'AttributeGroup', 'Classification'],
            'checkForConflicts'               => true,
            'locale'                          => 'main',
            'massCreateMaxChunkSize'          => 3000,
            'massUpdateMaxCountWithoutJob'    => 200,
            'massUpdateMinChunkSize'          => 400,
            'massUpdateMaxChunkSize'          => 3000,
            'massDeleteMaxCountWithoutJob'    => 200,
            'massDeleteMinChunkSize'          => 400,
            'massDeleteMaxChunkSize'          => 3000,
            'massRestoreMaxCountWithoutJob'   => 200,
            'massRestoreMinChunkSize'         => 400,
            'massRestoreMaxChunkSize'         => 3000,
            'massDownloadMinChunkSize'        => 400,
            'massDownloadMaxChunkSize'        => 1000,
            'maxTransactionJobsPerProcess'    => 1000,
            'frontendTimeout'                 => 120
        ];
    }
}

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

use Atro\Core\ModuleManager\AbstractModule;
use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\OAuth\Keys;
use Atro\Repositories\SoftwarePackage as SoftwarePackageRepository;

final class Config
{
    private const string CONFIG_PATH = 'data/config.php';

    /**
     * The system parameters: they override data/config.php and cannot be changed by
     * set(), remove() or the Settings form.
     */
    private const string SYSTEM_CONFIG_PATH = 'data/config.system.php';

    /**
     * Parameters the installation moves to data/config.system.php, see moveSystemParameters().
     */
    private const SYSTEM_KEYS
        = [
            'database',
            'logger',
            'amountOfDbDumps',
            'filesPath',
            'thumbnailsPath',
            'encryptionKey',
            PasswordHash::PEPPER_CONFIG_KEY,
            Keys::ENCRYPTION_KEY_CONFIG_KEY,
        ];

    /**
     * Keys the core derives on every load - see applyComputedKeys(). They are
     * never stored in data/config.php and never writable.
     */
    private const COMPUTED_KEYS
        = [
            'locales',
            'mainLanguage',
            'inputLanguageList',
            'isMultilangActive',
            'onlyStableReleases',
            'passwordChangeRequestAvailable',
        ];

    /**
     * What the core itself contributes to the config, as 'key' => provider.
     * A key may be segmented: 'referenceData.Language' lands in
     * $config['referenceData']['Language']. Each provider owns its storage and
     * decides which of its fields it exposes, through its own getConfigData().
     * Modules contribute theirs via AbstractModule::getConfigAdditionalData().
     */
    private const ADDITIONAL_CONFIG_PROVIDERS
        = [
            'referenceData.Language'       => \Atro\Repositories\Language::class,
            'referenceData.Locale'         => \Atro\Repositories\Locale::class,
            'referenceData.Style'          => \Atro\Repositories\Style::class,
            'referenceData.AttributePanel' => \Atro\Repositories\AttributePanel::class,
            'referenceData.Background'     => \Atro\Repositories\Background::class,
        ];

    /**
     * System config defaults, merged under data/config.php on load
     */

    private ?array $additionalConfigCache = null;

    private array $data = [];
    private array $systemKeys = [];
    private array $changedData = [];
    private array $removeData = [];

    /**
     * Config data as stored on disk, with data/config.system.php laid over data/config.php.
     */
    public static function load(): array
    {
        return self::applySystemConfig(self::loadStored(), self::loadSystem());
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
        $data['encryptionKey'] = bin2hex(random_bytes(16));
        $data[PasswordHash::PEPPER_CONFIG_KEY] = PasswordHash::generatePepper();
        $data[Keys::ENCRYPTION_KEY_CONFIG_KEY] = Keys::generateEncryptionKey();

        return self::writeAtomically(self::CONFIG_PATH, self::exportPhp($data));
    }

    /**
     * Moves the system parameters from data/config.php to data/config.system.php when the
     * system is installed: the installer writes them to data/config.php, so their values
     * there are the latest ones. A data/config.system.php maintained by hand is never written,
     * as writing it would replace its expressions, e.g. getenv(), with their values: what it
     * defines is only removed from data/config.php, where it is overridden anyway.
     */
    public function moveSystemParameters(): void
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return;
        }

        $stored = self::loadStored();
        $system = self::loadSystem();
        $keys = array_flip(self::SYSTEM_KEYS);

        if (self::isSystemConfigGenerated($system)) {
            $moved = array_intersect_key($stored, $keys);
            if (empty($moved)) {
                return;
            }

            // the system file first: an interruption leaves the parameters in both files, with the same values
            if (!self::writeAtomically(self::SYSTEM_CONFIG_PATH, self::exportPhp(array_merge($system, $moved)))) {
                throw new \RuntimeException(sprintf('Cannot write %s.', self::SYSTEM_CONFIG_PATH));
            }

            $data = array_diff_key($stored, $moved);
        } else {
            $data = self::removeOverridden($stored, array_intersect_key($system, $keys));
            if ($data === $stored) {
                return;
            }
        }

        if (!self::writeAtomically(self::CONFIG_PATH, self::exportPhp($data))) {
            throw new \RuntimeException(sprintf('Cannot write %s.', self::CONFIG_PATH));
        }

        $this->loadConfig(true);
    }

    /**
     * Whether the parameter is defined in data/config.system.php. A segmented name
     * is checked by its top-level key, as the whole top-level key is protected.
     */
    public function isSystemKey(string $name): bool
    {
        $this->loadConfig();

        return in_array(explode('.', $name)[0], $this->systemKeys, true);
    }

    /**
     * Whether the installation of the system is complete. The installer sets it as its very last step, so a failed
     * installation leaves the system not installed: it then works with the files only.
     */
    public function isInstalled(): bool
    {
        return !empty($this->get('isInstalled'));
    }

    public function getSiteUrl(): string
    {
        return rtrim($this->get('siteUrl'), '/');
    }

    /**
     * Drops the in-memory cache of everything the config computes on load.
     * Called by DataManager when the caches are cleared.
     */
    public function clearCache(): void
    {
        $this->additionalConfigCache = null;
    }
    
    public function getAdditionalConfigKeys(): array
    {
        $keys = [];

        foreach (array_keys($this->getAdditionalConfigData()) as $path) {
            $keys[] = strtok($path, '.');
        }

        return array_values(array_unique($keys));
    }

    public function get(string $name, mixed $default = null): mixed
    {
        $keys = explode('.', $name);

        $lastBranch = $this->loadConfig();
        foreach ($keys as $keyName) {
            if (is_array($lastBranch) && isset($lastBranch[$keyName])) {
                $lastBranch = $lastBranch[$keyName];
            } elseif (is_object($lastBranch) && isset($lastBranch->$keyName)) {
                $lastBranch = $lastBranch->$keyName;
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

        $this->assertWritable($name);

        $this->data[$name] = $value;
        $this->changedData[$name] = $value;
    }

    public function remove(string $name): bool
    {
        $this->loadConfig();

        $this->assertWritable($name);

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

        // only data/config.php is written, data/config.system.php stays untouched
        $data = self::loadStored();

        if (empty($data)) {
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

        if (!self::writeAtomically(self::CONFIG_PATH, self::exportPhp($data))) {
            return false;
        }

        $this->changedData = [];
        $this->removeData = [];
        $this->loadConfig(true);

        return true;
    }

    protected function loadConfig(bool $reload = false): array
    {
        if ($reload || empty($this->data)) {
            // checked before it lands in $data: leaving a broken config in place
            // would silence the check on every later call
            $system = self::loadSystem();
            $data = self::applySystemConfig(self::loadStored(), $system);

            $this->assertNoConflictsWithStoredConfig($data);

            $this->data = $data;
            $this->systemKeys = array_keys($system);
        }

        $this->applyAdditionalConfigData();
        $this->applyComputedKeys();

        return $this->data;
    }

    /**
     * Lays the contributed entries over the config. Runs on every load, so it
     * must stay idempotent - the conflict check lives in
     * assertNoConflictsWithStoredConfig() and runs against the stored data only.
     */
    private function applyAdditionalConfigData(): void
    {
        foreach ($this->getAdditionalConfigData() as $path => $value) {
            self::writePath($this->data, $path, $value);
        }
    }

    /**
     * A provider may only add: a key that is already in data/config.php means
     * the provider and the stored config disagree, and that is a bug worth
     * failing on rather than resolving silently.
     */
    private function assertNoConflictsWithStoredConfig(array $data): void
    {
        foreach (array_keys($this->getAdditionalConfigData()) as $path) {
            if (self::pathExists($data, $path)) {
                throw new \LogicException(
                    sprintf("Config key '%s' is contributed but already exists.", $path)
                );
            }
        }
    }

    /**
     * Entries contributed to the config, collected once. The core lists its own
     * providers here; modules add theirs through getConfigAdditionalData(). The
     * module list is read directly, without the container, because the config
     * is built before module instances exist.
     */
    private function getAdditionalConfigData(): array
    {
        if ($this->additionalConfigCache !== null) {
            return $this->additionalConfigCache;
        }

        // collected locally: a half-filled cache after a failure would make the
        // next call return silently broken data instead of failing again
        $collected = [];
        $providers = [];

        foreach (self::ADDITIONAL_CONFIG_PROVIDERS as $path => $class) {
            self::collectItem($path, $class::getConfigData(), 'the core', $collected, $providers);
        }

        foreach (ModuleManager::getList() as $module) {
            $class = "\\$module\\Module";

            if (!class_exists($class) || !is_a($class, AbstractModule::class, true)) {
                continue;
            }

            foreach ($class::getConfigAdditionalData() as $path => $value) {
                self::collectItem($path, $value, $module, $collected, $providers);
            }
        }

        return $this->additionalConfigCache = $collected;
    }

    private static function collectItem(
        string $path,
        mixed  $value,
        string $provider,
        array  &$collected,
        array  &$providers
    ): void {
        if (isset($providers[$path])) {
            throw new \LogicException(sprintf(
                "Config key '%s' is contributed by both %s and %s.", $path, $providers[$path], $provider
            ));
        }

        $providers[$path] = $provider;

        if (!empty($value)) {
            $collected[$path] = $value;
        }
    }

    /**
     * Keys the core derives from the contributed data.
     */
    private function applyComputedKeys(): void
    {
        $this->data['locales'] = [];
        $this->data['inputLanguageList'] = [];

        foreach ($this->data['referenceData']['Locale'] ?? [] as $row) {
            if (empty($row['id'])) {
                continue;
            }

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

        foreach ($this->data['referenceData']['Language'] ?? [] as $row) {
            if (empty($row['role'])) {
                continue;
            }

            if ($row['role'] === 'main') {
                $this->data['mainLanguage'] = $row['code'];
            } elseif ($row['role'] === 'additional') {
                $this->data['inputLanguageList'][] = $row['code'];
            }
        }

        $this->data['isMultilangActive'] = !empty($this->data['inputLanguageList']);

        $minimumStability = SoftwarePackageRepository::getComposerData()['minimum-stability'] ?? 'stable';

        $this->data['onlyStableReleases'] = $minimumStability === 'stable';

        $this->data['passwordChangeRequestAvailable'] = !empty($this->data['notificationSmtpConnectionId']);
    }

    private static function pathExists(array $data, string $path): bool
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return false;
            }
            $data = $data[$key];
        }

        return true;
    }

    private static function writePath(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);

        $branch = &$data;
        foreach ($keys as $key) {
            if (!isset($branch[$key]) || !is_array($branch[$key])) {
                $branch[$key] = [];
            }
            $branch = &$branch[$key];
        }

        $branch[$last] = $value;
    }

    /**
     * The defaults stand in for data/config.php before the installation has created
     * the file - that is a normal state, unlike a file that exists but does not hold an array.
     */
    private static function loadStored(): array
    {
        if (!file_exists(self::CONFIG_PATH)) {
            return self::getDefaults();
        }

        return self::includeFile(self::CONFIG_PATH);
    }

    /**
     * data/config.system.php is optional: a missing file defines no system parameters.
     */
    private static function loadSystem(): array
    {
        if (!file_exists(self::SYSTEM_CONFIG_PATH)) {
            return [];
        }

        return self::includeFile(self::SYSTEM_CONFIG_PATH);
    }

    private static function includeFile(string $path): array
    {
        $data = include $path;

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Config file %s is corrupted.', $path));
        }

        return $data;
    }

    /**
     * Lays the system parameters over the stored ones. Associative arrays are merged
     * key by key, anything else - lists included - is replaced as a whole.
     */
    private static function applySystemConfig(array $data, array $system): array
    {
        foreach ($system as $key => $value) {
            if (self::isMap($value) && self::isMap($data[$key] ?? null)) {
                $data[$key] = self::applySystemConfig($data[$key], $value);
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Whether data/config.system.php is missing or exactly what writing its values gives, i.e.
     * holds plain values only. Anything else - an expression, a comment, another formatting -
     * means the file is maintained by hand.
     */
    private static function isSystemConfigGenerated(array $system): bool
    {
        if (!file_exists(self::SYSTEM_CONFIG_PATH)) {
            return true;
        }

        return file_get_contents(self::SYSTEM_CONFIG_PATH) === self::exportPhp($system);
    }

    /**
     * Removes from the stored parameters what the system ones override - the counterpart of
     * applySystemConfig(): associative arrays key by key, anything else as a whole.
     */
    private static function removeOverridden(array $data, array $system): array
    {
        foreach ($system as $key => $value) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (self::isMap($value) && self::isMap($data[$key])) {
                $data[$key] = self::removeOverridden($data[$key], $value);
                if ($data[$key] === []) {
                    unset($data[$key]);
                }
            } else {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private static function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && !array_is_list($value);
    }

    private function assertWritable(string $name): void
    {
        if (in_array($name, $this->systemKeys, true)) {
            throw new \LogicException(
                sprintf("Config key '%s' is defined in %s and cannot be written.", $name, self::SYSTEM_CONFIG_PATH)
            );
        }

        if (in_array($name, $this->getReadOnlyKeys(), true)) {
            throw new \LogicException(
                sprintf("Config key '%s' is computed on load and cannot be written.", $name)
            );
        }
    }

    /**
     * Keys that may not be written: the ones the core derives on load, plus
     * everything contributed by the providers. A segmented contributed key
     * protects its root, since set() only ever writes top-level keys.
     */
    private function getReadOnlyKeys(): array
    {
        $keys = self::COMPUTED_KEYS;

        foreach (array_keys($this->getAdditionalConfigData()) as $path) {
            $keys[] = strtok($path, '.');
        }

        return array_unique($keys);
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
    private static function writeAtomically(string $path, string $content): bool
    {
        $tmp = $path . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, $content) === false) {
            return false;
        }

        // a fresh file gets umask permissions - keep whatever the file had, a new one gets the ones of data/config.php
        $source = file_exists($path) ? $path : self::CONFIG_PATH;
        if (file_exists($source) && ($perms = fileperms($source)) !== false) {
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
            'outboundEmailFromAddress'        => '',
            'logger'                          => [
                'path'          => 'data/logs/atro.log',
                'level'         => 'WARNING', /** DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY */
                'rotation'      => true,
                'maxFileNumber' => 7,
            ],
            'assignmentEmailNotifications'    => false,
            'disabledCountQueryEntityList'    => [],
            'sessionLifetime'               => 0,
            'passwordChangeRequestResendInterval' => 1,
            'passwordChangeRequestLifetime'   => 15,
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
            'massErasePersonalDataMaxCountWithoutJob' => 200,
            'massErasePersonalDataMinChunkSize'       => 400,
            'massErasePersonalDataMaxChunkSize'       => 3000,
            'massRestoreMinChunkSize'         => 400,
            'massRestoreMaxChunkSize'         => 3000,
            'massDownloadMinChunkSize'        => 400,
            'massDownloadMaxChunkSize'        => 1000,
            'maxTransactionJobsPerProcess'    => 1000,
            'frontendTimeout'                 => 120
        ];
    }
}

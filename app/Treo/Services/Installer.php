<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\Core\Exceptions;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\PasswordHash;
use Treo\Core\Utils\Util;
use Espo\Entities\User;
use Treo\Core\Utils\Config;
use Treo\Core\Utils\Language;
use Treo\Core\EventManager\Event;

/**
 * Service Installer
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Installer extends AbstractService
{

    /**
     * @var PasswordHash
     */
    protected $passwordHash = null;

    /**
     * @var null|array
     */
    protected $installConfig = null;

    /**
     * @return string
     */
    public static function generateTreoId(): string
    {
        return substr(md5(md5(Util::generateId() . "-treo-salt-") . Util::generateId()), 0, 21);
    }

    /**
     * Get requireds list
     *
     * @return array
     */
    public function getRequiredsList(): array
    {
        // prepare result
        $result = [];

        if (!empty($data = $this->getInstallConfig()['requirements'])) {
            // for php version
            $phpVersion = self::prepareVersion(phpversion());
            $result[] = [
                'name'       => $this->translate('phpVersion', 'requirements', 'Installer'),
                'validValue' => ">=" . $data['phpVersion'],
                'value'      => $phpVersion,
                'isValid'    => version_compare($phpVersion, $data['phpVersion'], '>=')
            ];

            // for php extensions
            foreach ($data['phpRequires'] as $require) {
                // is ext valid?
                $isValid = extension_loaded($require);

                $result[] = [
                    'name'       => $this->translate($require, 'requirements', 'Installer'),
                    'validValue' => $this->translate('On'),
                    'value'      => ($isValid) ? $this->translate('On') : $this->translate('Off'),
                    'isValid'    => $isValid
                ];
            }

            // for php settings
            foreach ($data['phpSettings'] as $setting => $value) {
                // get system value
                $systemValue = ini_get($setting);

                // prepare value
                $preparedSystemValue = $systemValue;
                $preparedValue = $value;
                if (!in_array($setting, ['max_execution_time', 'max_input_time'])) {
                    $preparedSystemValue = $this->convertToBytes($systemValue);
                    $preparedValue = $this->convertToBytes($value);
                }

                $result[] = [
                    'name'       => $this->translate($setting, 'requirements', 'Installer'),
                    'validValue' => '>= ' . $value,
                    'value'      => $systemValue,
                    'isValid'    => ($preparedSystemValue >= $preparedValue || in_array($systemValue, [0, -1]))
                ];
            }

            // for mysql version
            $mysqlVersion = self::prepareVersion($this->getMysqlVersion(), true);
            $result[] = [
                'name'       => $this->translate('mysqlVersion', 'requirements', 'Installer'),
                'validValue' => '>= ' . $data['mysqlVersion'],
                'value'      => $mysqlVersion,
                'isValid'    => version_compare($mysqlVersion, $data['mysqlVersion'], '>=')
            ];
        }

        return $result;
    }

    /**
     *  Generate default config if not exists
     *
     * @return bool
     * @throws Exceptions\Error
     *
     * @throws Exceptions\Forbidden
     */
    public function generateConfig(): bool
    {
        $result = false;

        // check if is install
        if ($this->isInstalled()) {
            throw new Exceptions\Forbidden($this->translateError('alreadyInstalled'));
        }

        /** @var Config $config */
        $config = $this->getConfig();

        $pathToConfig = $config->getConfigPath();

        // get default config
        if (empty($defaultConfig = $config->getDefaults())) {
            throw new Exceptions\Error();
        }

        // get permissions
        if (!empty($owner = $this->getDefaultOwner(true))) {
            $defaultConfig['defaultPermissions']['user'] = $owner;
        }
        if (!empty($group = $this->getDefaultGroup(true))) {
            $defaultConfig['defaultPermissions']['group'] = $group;
        }

        $defaultConfig['passwordSalt'] = $this->generateSalt();
        $defaultConfig['cryptKey'] = $this->generateKey();

        // create config if not exists
        if (!$this->fileExists($pathToConfig)) {
            $result = $this->putPhpContents($pathToConfig, $defaultConfig, true);
        }

        return $result;
    }

    /**
     * Get translations for installer
     *
     * @return array
     */
    public function getTranslations(): array
    {
        // create language
        $language = $this->getLanguage();

        $result = $language->get('Installer');

        // add languages
        $languages = $language->get('Global.options.language');

        $result['labels']['languages'] = $languages;

        return $result;
    }

    /**
     * Get license and languages
     *
     * @return array
     */
    public function getLicenseAndLanguages(): array
    {
        // get languages data
        $result = [
            'languageList' => $this->getConfig()->get('languageList'),
            'language'     => $this->getConfig()->get('language'),
            'license'      => ''
        ];

        // get license
        $license = $this->getContents('LICENSE.txt');
        $result['license'] = $license ? $license : '';

        return $result;
    }

    /**
     * Get default dataBase settings
     *
     * @return array
     */
    public function getDefaultDbSettings(): array
    {
        return $this->getConfig()->get('database');
    }

    /**
     * Set language
     *
     * @param $lang
     *
     * @return array
     */
    public function setLanguage(string $lang): array
    {
        $result = ['status' => false, 'message' => ''];

        if (!in_array($lang, $this->getConfig()->get('languageList'))) {
            $result['message'] = $this->translateError('languageNotCorrect');
            $result['status'] = false;
        } else {
            $this->getConfig()->set('language', $lang);
            $result['status'] = $this->getConfig()->save();
        }

        return $result;
    }

    /**
     * Set DataBase settings
     *
     * @param array $data
     *
     * @return array
     */
    public function setDbSettings(array $data): array
    {
        $result = ['status' => false, 'message' => ''];

        /** @var Config $config */
        $config = $this->getConfig();

        $dbParams = $config->get('database');

        // prepare input params
        $dbSettings = $this->prepareDbParams($data);

        try {
            // check connect to db
            $this->isConnectToDb($dbSettings);

            // update config
            $config->set('database', array_merge($dbParams, $dbSettings));

            $result['status'] = $config->save();
        } catch (\Exception $e) {
            $result['message'] = $this->translateError('notCorrectDatabaseConfig');
            $result['status'] = false;
        }

        return $result;
    }

    /**
     * Create admin
     *
     * array $params
     *
     * @param $params
     *
     * @return array
     */
    public function createAdmin(array $params): array
    {
        // prepare result
        $result = [
            'status'  => true,
            'message' => ''
        ];

        // check password
        if ($params['password'] !== $params['confirmPassword']) {
            // prepare result
            $result = [
                'status'  => false,
                'message' => $this->translateError('differentPass')
            ];
        } else {
            try {
                // create fake system user
                $this->createFakeSystemUser();

                // create user
                $user = $this->createSuperAdminUser($params['username'], $params['password']);

                // set installed
                $this->getConfig()->set('isInstalled', true);

                // save config
                $this->getConfig()->save();

                $this->dispatch('Installer', 'afterInstallSystem', new Event());
            } catch (\Exception $e) {
                // prepare result
                $result = [
                    'status'  => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Check connect to DB
     *
     * @param $dbSettings
     *
     * @return array
     */
    public function checkDbConnect(array $dbSettings): array
    {
        $result = ['status' => false, 'message' => ''];

        try {
            $result['status'] = $this->isConnectToDb($this->prepareDbParams($dbSettings));
        } catch (\PDOException $e) {
            $result['status'] = false;
            $result['message'] = $this->translateError('notCorrectDatabaseConfig');
        }

        return $result;
    }

    /**
     * Check if is install
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        $config = $this->getConfig();

        return $this->fileExists($config->getConfigPath()) && $config->get('isInstalled');
    }

    /**
     * Check permissions
     *
     * @return bool
     * @throws Exceptions\InternalServerError
     */
    public function checkPermissions(): bool
    {
        $this->setMapPermission();

        $error = $this->getLastError();

        if (!empty($error)) {
            $message = is_array($error) ? implode($error, ' ;') : (string)$error;

            throw new Exceptions\InternalServerError($message);
        }

        return true;
    }

    /**
     * Prepare DB params
     *
     * @param array $data
     *
     * @return array
     */
    protected function prepareDbParams(array $data): array
    {
        // prepare params
        return [
            'host'     => (string)$data['host'],
            'port'     => isset($data['port']) ? (string)$data['port'] : '',
            'dbname'   => (string)$data['dbname'],
            'user'     => (string)$data['user'],
            'password' => isset($data['password']) ? (string)$data['password'] : ''
        ];
    }


    /**
     * Check connect to db
     *
     * @param array $dbSettings
     *
     * @return bool
     */
    protected function isConnectToDb(array $dbSettings)
    {
        $port = !empty($dbSettings['port']) ? '; port=' . $dbSettings['port'] : '';

        $dsn = 'mysql' . ':host=' . $dbSettings['host'] . $port . ';dbname=' . $dbSettings['dbname'] . ';';

        $this->createDataBaseIfNotExists($dbSettings, $port);

        new \PDO($dsn, $dbSettings['user'], $dbSettings['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]);

        return true;
    }

    /**
     * Create database if not exists
     *
     * @param array  $dbSettings
     * @param string $port
     */
    protected function createDataBaseIfNotExists(array $dbSettings, string $port)
    {
        $dsn = 'mysql' . ':host=' . $dbSettings['host'] . $port;

        $pdo = new \PDO(
            $dsn,
            $dbSettings['user'],
            $dbSettings['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $dbSettings['dbname'] . "`");
    }

    /**
     * Get file manager
     *
     * @return FileManager
     */
    protected function getFileManager(): FileManager
    {
        return $this->getContainer()->get('fileManager');
    }

    /**
     * Get passwordHash
     *
     * @return PasswordHash
     */
    protected function getPasswordHash(): PasswordHash
    {
        if (!isset($this->passwordHash)) {
            $config = $this->getConfig();
            $this->passwordHash = new PasswordHash($config);
        }

        return $this->passwordHash;
    }

    /**
     * Translate error
     *
     * @param string $error
     *
     * @return mixed
     */
    protected function translateError(string $error): string
    {
        return $this->translate($error, 'errors', 'Installer');
    }

    /**
     * Get install config
     *
     * @return array
     */
    protected function getInstallConfig(): array
    {
        if (is_null($this->installConfig)) {
            // prepare path to file
            $configFile = CORE_PATH . '/Treo/Configs/Install.php';

            // get data
            $this->installConfig = include $configFile;
        }

        return $this->installConfig;
    }

    /**
     * Get mysql version
     *
     * @return string|null
     */
    protected function getMysqlVersion(): ?string
    {
        $sth = $this->getEntityManager()->getPDO()->prepare("SHOW VARIABLES LIKE 'version'");
        $sth->execute();
        $res = $sth->fetch(\PDO::FETCH_NUM);

        $version = empty($res[1]) ? null : $res[1];

        return $version;
    }

    /**
     * Convert to bytes
     *
     * @param string $value
     *
     * @return int
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtoupper(substr($value, -1));

        switch ($last) {
            case 'G':
                $value = (int)$value * 1024 * 1024 * 1024;
                break;
            case 'M':
                $value = (int)$value * 1024 * 1024;
                break;
            case 'K':
                $value = (int)$value * 1024;
                break;
            default:
                $value = (int)$value;
                break;
        }

        return $value;
    }

    /**
     * Prepare version
     *
     * @param string $version
     * @param bool   $patch
     *
     * @return string|null
     */
    protected static function prepareVersion(string $version, bool $patch = false): ?string
    {
        // prepare result
        $result = null;

        $data = explode(".", $version);
        if (isset($data[0]) && isset($data[1])) {
            $result = $data[0] . '.' . $data[1];
        }

        if ($patch && isset($data[2])) {
            $result .= '.' . (int)$data[2];
        }

        return $result;
    }

    /**
     * Create super admin user
     *
     * @param string $username
     * @param string $password
     *
     * @return User
     * @throws Exceptions\Error
     */
    protected function createSuperAdminUser(string $username, string $password): User
    {
        // prepare data
        $passwordHash = $this->getPasswordHash()->hash($password);
        $today = (new \DateTime())->format('Y-m-d H:i:s');

        $sql
            = "INSERT INTO `user` (id, user_name, password, last_name, is_admin, created_at)
					VALUES ('1', '$username', '$passwordHash', 'Admin', '1', '$today')";

        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $this->getEntityManager()->getEntity('User', 1);
    }

    protected function putPhpContents(string $path, array $data, bool $withObjects = false): bool
    {
        return $this->getFileManager()->putPhpContents($path, $path, $withObjects);
    }

    /**
     * Checks whether a file or directory exists
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }

    /**
     * Get file contents into the string
     *
     * @param string $path
     *
     * @return mixed
     */
    protected function getContents(string $path)
    {
        return $this->getFileManager()->getContents($path);
    }

    /**
     * Create fake system user
     *
     * @throws Exceptions\Error
     */
    protected function createFakeSystemUser(): void
    {
        $systemUser = $this->getEntityManager()->getEntity('User');
        $systemUser->set('id', 'system');

        // set system user to container
        $this->getContainer()->setUser($systemUser);

        // rebuild database
        $this->getContainer()->get('dataManager')->rebuild();
    }

    /**
     * Set permission
     */
    protected function setMapPermission(): void
    {
        $this->getFileManager()->getPermissionUtils()->setMapPermission();
    }

    /**
     * Get last permission error
     *
     * @return array|string
     */
    protected function getLastError()
    {
        return $this->getFileManager()->getPermissionUtils()->getLastError();
    }

    /**
     * Get default owner user id
     *
     * @param bool $usePosix
     *
     * @return int
     */
    protected function getDefaultOwner(bool $usePosix)
    {
        return $this->getFileManager()->getPermissionUtils()->getDefaultOwner($usePosix);
    }

    /**
     * get default group user id
     *
     * @param bool $usePosix
     *
     * @return int
     */
    protected function getDefaultGroup(bool $usePosix)
    {
        return $this->getFileManager()->getPermissionUtils()->getDefaultGroup($usePosix);
    }

    /**
     * Generate a new salt
     *
     * @return string
     */
    protected function generateSalt()
    {
        return $this->getPasswordHash()->generateSalt();
    }

    /**
     * Generate key
     *
     * @return mixed
     */
    protected function generateKey()
    {
        return $this->getContainer()->get('crypt')->generateKey();
    }

    /**
     * @return Language
     */
    protected function getLanguage()
    {
        return new Language(
            $this->getConfig()->get('language'),
            $this->getContainer()->get('fileManager'),
            $this->getContainer()->get('metadata')
        );
    }
}

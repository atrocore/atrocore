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

declare(strict_types=1);

namespace Espo\Services;

use Espo\Console\AbstractConsole;
use Espo\Core\Exceptions;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\PasswordHash;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Treo\Core\ModuleManager\Manager;

class Installer extends \Espo\Core\Templates\Services\HasContainer
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
        $dbConnection = $this->checkDbConnect($data);
        if (!$dbConnection['status']) {
            return $dbConnection;
        }

        $message = '';
        try {
            $this->getConfig()->set('database', array_merge($this->getConfig()->get('database', []), $this->prepareDbParams($data)));
            $this->getConfig()->save();
        } catch (\Exception $e) {
            $message = $this->translateError('filePermissionsError');
        }

        return ['status' => empty($message), 'message' => $message];
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
        // check password
        if ($params['password'] !== $params['confirmPassword']) {
            return ['status' => false, 'message' => $this->translateError('differentPass')];
        }

        try {
            // create fake system user
            $this->createFakeSystemUser();

            // prepare database for installation
            $this->prepareDataBase();

            // create user
            $user = $this->createSuperAdminUser($params['username'], $params['password']);

            // set installed
            $this->getConfig()->set('isInstalled', true);
            $this->getConfig()->save();
        } catch (\Exception $e) {
            $GLOBALS['log']->error('Installer Error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return ['status' => false, 'message' => $e->getMessage()];
        }

        // after install
        $this->afterInstall();

        return ['status' => true, 'message' => ''];
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
        $message = '';

        try {
            $this->isConnectToDb($this->prepareDbParams($dbSettings));
        } catch (\PDOException $e) {
            $message = $this->translateError('notCorrectDatabaseConfig') . ': ' . $e->getMessage();
        }

        return ['status' => empty($message), 'message' => $message];
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
     * @inheritDoc
     */
    protected function translate(string $label, string $category = 'labels', string $scope = 'Global', array $requiredOptions = null): string
    {
        return $this
            ->getContainer()
            ->get('baseLanguage')
            ->translate($label, $category, $scope, $requiredOptions);
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
    }

    /**
     * Remove all existing tables and rub rebuild
     */
    protected function prepareDataBase()
    {
        /** @var array $dbParams */
        $dbParams = $this->getConfig()->get('database');

        // get existing db tables
        $tables = $this
            ->getEntityManager()
            ->nativeQuery("SELECT table_name FROM information_schema.tables WHERE table_schema='{$dbParams['dbname']}';")
            ->fetchAll(\PDO::FETCH_ASSOC);

        // drop all existing tables if it needs
        if (!empty($tables)) {
            foreach ($tables as $row) {
                $tableName = null;
                if (!empty($row['table_name'])) {
                    $tableName = $row['table_name'];
                }
                if (!empty($row['TABLE_NAME'])) {
                    $tableName = $row['TABLE_NAME'];
                }

                if ($tableName) {
                    $this->getEntityManager()->nativeQuery("DROP TABLE `$tableName`");
                }
            }
        }

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
     * @return Language
     */
    protected function getLanguage()
    {
        return new Language($this->getContainer(), $this->getConfig()->get('language'));
    }

    protected function afterInstall(): void
    {
        // Generate application ID
        $this->generateAppId();

        // create files in data dir
        file_put_contents('data/publicData.json', '{}');

        /**
         * Run after install script if it needs
         */
        $file = 'data/after_install_script.php';
        if (file_exists($file)) {
            include_once $file;
            unlink($file);
        }

        $this->exec(
            "INSERT INTO `locale` (id, `name`, `language`, date_format, time_zone, week_start, time_format, thousand_separator, decimal_mark) VALUES ('1', 'Main', 'en_US', 'DD.MM.YYYY', 'UTC', 'monday', 'HH:mm', '.', ',')"
        );
        $this->exec(
            "INSERT INTO scheduled_job (id, `name`, job, `status`, scheduling) VALUES ('ComposerAutoUpdate', 'Automatic system update', 'ComposerAutoUpdate', 'Active', '0 0 * * SUN')"
        );
        $this->exec(
            "INSERT INTO scheduled_job (id, `name`, job, `status`, scheduling) VALUES ('TreoCleanup','Old deleted data cleanup','TreoCleanup','Active','0 0 1 * *')"
        );

        foreach ($this->getModuleManager()->getModulesList() as $name) {
            try {
                $this->getModuleManager()->getModuleInstallDeleteObject($name)->afterInstall();
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("After Install Module Error: {$e->getMessage()}");
            }
        }

        // refresh translations
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php refresh translations >/dev/null");
    }

    /**
     * Generate application ID
     */
    protected function generateAppId(): void
    {
        // generate id
        $appId = substr(md5(md5(Util::generateId() . "-atro-salt-") . Util::generateId()), 0, 21);

        if (!empty($_SERVER['ATRO_APP_ID'])) {
            $appId = $_SERVER['ATRO_APP_ID'];
        }

        // set to config
        $this->getConfig()->set('appId', $appId);
        $this->getConfig()->save();
    }

    private function exec(string $query): void
    {
        try {
            $this->getContainer()->get('pdo')->exec($query);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("PDO Error: {$e->getMessage()}. For query '$query'");
        }
    }

    /**
     * @return Manager
     */
    private function getModuleManager(): Manager
    {
        return $this->getContainer()->get('moduleManager');
    }
}

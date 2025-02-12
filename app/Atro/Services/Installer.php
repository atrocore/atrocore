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

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Templates\Services\HasContainer;
use Atro\Console\AbstractConsole;
use Atro\Core\ModuleManager\Manager;
use Atro\Migrations\V1Dot12Dot1;
use Atro\Migrations\V1Dot12Dot12;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Util;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Exceptions;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\PasswordHash;
use Espo\Entities\User;

class Installer extends HasContainer
{
    protected ?PasswordHash $passwordHash = null;

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

    public function getLicenseAndLanguages(): array
    {
        $license = $this->getContents('LICENSE.txt');

        $languages = [
            "de_DE" => "Deutsch",
            "en_US" => "English",
            "es_ES" => "Español",
            'fr_FR' => 'Français',
            "pl_PL" => "Polski",
            "ru_RU" => "Русский",
            'uk_UA' => 'Українська'
        ];

        return [
            'languages'    => $languages,
            'languageList' => array_keys($languages),
            'language'     => $this->getConfig()->get('language') ?? 'en_US',
            'license'      => $license ?? ''
        ];
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

    public function setLanguage(string $lang): array
    {
        $this->getConfig()->set('language', $lang);
        return [
            'status'  => $this->getConfig()->save(),
            'message' => '',
        ];
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
            $this->getConfig()->set('reportingEnabled', !empty($params['reportingEnabled']));
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
            'driver'   => (string)$data['driver'],
            'host'     => (string)$data['host'],
            'port'     => isset($data['port']) ? (string)$data['port'] : '',
            'dbname'   => (string)$data['dbname'],
            'user'     => (string)$data['user'],
            'password' => isset($data['password']) ? (string)$data['password'] : '',
            'charset'  => $data['driver'] === 'pdo_pgsql' ? 'utf8' : 'utf8mb4',
        ];
    }

    protected function isConnectToDb(array $dbSettings): bool
    {
        $system = str_replace('pdo_', '', $dbSettings['driver']);

        $port = !empty($dbSettings['port']) ? '; port=' . $dbSettings['port'] : '';

        $dsn = $system . ':host=' . $dbSettings['host'] . $port . ';dbname=' . $dbSettings['dbname'] . ';';

        try {
            $this->createDataBaseIfNotExists($system, $dbSettings, $port);
        } catch (\Throwable $e) {
        }

        new \PDO($dsn, $dbSettings['user'], $dbSettings['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]);

        return true;
    }

    protected function createDataBaseIfNotExists(string $system, array $dbSettings, string $port): void
    {
        $dsn = $system . ':host=' . $dbSettings['host'] . $port;

        $pdo = new \PDO(
            $dsn,
            $dbSettings['user'],
            $dbSettings['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING]
        );

        try {
            $pdo->exec("CREATE DATABASE " . $dbSettings['dbname']);
        } catch (\Throwable $e) {
        }
    }

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
        return [
            'requirements' => [
                'phpVersion'  => '7.1',
                'phpRequires' => [
                    'json',
                    'openssl',
                    'mbstring',
                    'zip',
                    'gd',
                    'curl',
                    'xml',
                    'exif'
                ],
                'phpSettings' => [
                    'max_execution_time'  => 180,
                    'max_input_time'      => 180,
                    'memory_limit'        => '256M',
                    'post_max_size'       => '20M',
                    'upload_max_filesize' => '20M'
                ]
            ]
        ];
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
        $connection = $this->getEntityManager()->getConnection();

        // prepare data
        $passwordHash = $this->getPasswordHash()->hash($password);
        $today = (new \DateTime())->format('Y-m-d H:i:s');

        $connection->createQueryBuilder()
            ->insert($connection->quoteIdentifier('user'))
            ->setValue('id', ':id')
            ->setValue($connection->quoteIdentifier('name'), ':name')
            ->setValue('last_name', ':name')
            ->setValue('user_name', ':userName')
            ->setValue('password', ':password')
            ->setValue('is_admin', ':isAdmin')
            ->setValue('created_at', ':createdAt')
            ->setParameters([
                'id'        => '1',
                'name'      => 'Admin',
                'userName'  => $username,
                'password'  => $passwordHash,
                'createdAt' => $today
            ])
            ->setParameter('isAdmin', true, Mapper::getParameterType(true))
            ->executeQuery();

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
     * Remove all existing tables and run rebuild
     */
    protected function prepareDataBase()
    {
        /** @var array $dbParams */
        $dbParams = $this->getConfig()->get('database');

        $tableSchema = $dbParams['driver'] === 'pdo_pgsql' ? 'public' : $dbParams['dbname'];

        // get existing db tables
        $tables = $this
            ->getEntityManager()
            ->getPDO()
            ->query("SELECT table_name FROM information_schema.tables WHERE table_schema='$tableSchema'")
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
                    $this->getEntityManager()->getPDO()->exec("DROP TABLE " . $this->getEntityManager()->getConnection()->quoteIdentifier($tableName));
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

    protected function getLanguage(): Language
    {
        return new Language($this->getContainer());
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
            $configFile = 'data/config.php';
            if (file_exists($configFile)) {
                $configData = include $configFile;
                if (!empty($configData['database']['driver']) && $configData['database']['driver'] !== 'pdo_pgsql') {
                    include_once $file;
                }
            }
            unlink($file);
        }

        @mkdir(ReferenceData::DIR_PATH);
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Locale.json', json_encode([
            'en_US' => [
                'id'                => 'main',
                'name'              => 'Main',
                'code'              => 'en_US',
                'languageCode'      => 'en_US',
                'dateFormat'        => 'DD.MM.YYYY',
                'timeZone'          => 'UTC',
                'weekStart'         => 'monday',
                'timeFormat'        => 'HH:mm',
                'thousandSeparator' => '.',
                'decimalMark'       => ',',
                'createdAt'         => date('Y-m-d H:i:s')
            ]
        ]));
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Language.json', json_encode([
            'en_US' => [
                'id'        => 'main',
                'name'      => 'English',
                'code'      => 'en_US',
                'role'      => 'main',
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ]));
        @file_put_contents(
            ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json',
            json_encode(\Atro\Migrations\V1Dot11Dot44::getDefaultEmailTemplates())
        );

        $toInsertRecords = [
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => 'ComposerAutoUpdate',
                    'name'           => 'Update system automatically',
                    'type'           => 'ComposerAutoUpdate',
                    'is_active'      => true,
                    'scheduling'     => '0 0 * * SUN',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ]
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => 'UpdateCurrencyExchangeViaECB',
                    'name'           => 'Update currency exchange via ECB',
                    'type'           => 'UpdateCurrencyExchangeViaECB',
                    'is_active'      => true,
                    'scheduling'     => '0 2 * * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ]
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => 'ClearEntities',
                    'name'           => 'Clear deleted data',
                    'type'           => 'ClearEntities',
                    'is_active'      => true,
                    'scheduling'     => '0 2 1 * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ]
            ],
            [
                'tableName' => 'scheduled_job',
                'data'      => [
                    'id'             => 'CheckUpdates',
                    'name'           => 'Check system updates',
                    'type'           => 'CheckUpdates',
                    'is_active'      => true,
                    'scheduling'     => '0 2 * * *',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'modified_at'    => date('Y-m-d H:i:s'),
                    'created_by_id'  => 'system',
                    'modified_by_id' => 'system',
                ]
            ]
        ];

        $conn = $this->getEntityManager()->getConnection();
        foreach ($toInsertRecords as $row) {
            $qb = $conn->createQueryBuilder();
            $qb->insert($conn->quoteIdentifier($row['tableName']));
            foreach ($row['data'] as $columnName => $value) {
                $qb->setValue($columnName, ":$columnName");
                $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
            }
            $qb->executeQuery();
        }

        foreach ($this->getModuleManager()->getModulesList() as $name) {
            try {
                $this->getModuleManager()->getModuleInstallDeleteObject($name)->afterInstall();
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("After Install Module Error: {$e->getMessage()}");
            }
        }

        \Atro\Migrations\V1Dot10Dot0::createDefaultStorage($this->getEntityManager()->getConnection());
        \Atro\Migrations\V1Dot10Dot0::createDefaultFileTypes($this->getEntityManager()->getConnection());

        \Atro\Migrations\V1Dot10Dot50::createNotificationDefaultNotificationProfile($this->getEntityManager()->getConnection(), $this->getConfig());

        // create default email templates
        $emailTemplates = [];
        foreach (\Atro\Migrations\V1Dot10Dot50::getDefaultRules() as $rule) {
            if (!empty($rule['templates'])) {
                $templates = $rule['templates'];
                foreach ($templates as $type => $template) {
                    if ($type !== 'email') {
                        continue;
                    }
                    $emailTemplates[$template['id']]['id'] = $template['id'];
                    $emailTemplates[$template['id']]['code'] = $template['id'];
                    $emailTemplates[$template['id']]['name'] = $template['name'];
                    $emailTemplates[$template['id']]['subject'] = $template['data']['field']['subject'] ?? '';
                    $emailTemplates[$template['id']]['body'] = $template['data']['field']['body'] ?? '';
                    $emailTemplates[$template['id']]['createdAt'] = date('Y-m-d H:i:s');
                }
            }
        }
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json', json_encode($emailTemplates));

        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'HtmlSanitizer.json', json_encode([V1Dot12Dot1::getDefaultHtmlSanitizer()]));

        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Style.json', json_encode(V1Dot12Dot12::getDefaultStyles()));

        $this->createDefaultLayoutProfile();

        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php refresh translations >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php regenerate lists >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php regenerate measures >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php regenerate ui handlers >/dev/null");
    }

    protected function createDefaultLayoutProfile()
    {
        $defaultId = 'default';

        try {
            $menus = ['Folder', 'File'];

            if (class_exists('\Pim\Module')) {
                $menus = array_merge($menus,  [
                    'Association',
                    'Attribute',
                    'AttributeGroup',
                    'Brand',
                    'Category',
                    'Catalog',
                    'Channel',
                    'Product',
                    'Classification'
                ]);
            }

            if (class_exists('\Export\Module')) {
                $menus[] = 'ExportFeed';
            }

            if (class_exists('\Import\Module')) {
                $menus[] = 'ImportFeed';
            }
            // create default profile
            $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->insert('layout_profile')
                ->values([
                    'id'               => ':id',
                    'name'             => ':name',
                    'is_active'        => ':true',
                    'is_default'       => ':true',
                    'navigation'       => ':navigation',
                    'dashboard_layout' => ':dashboardLayout',
                ])->setParameters([
                    'id'   => $defaultId,
                    'name' => 'Standard',
                    'navigation' => json_encode($menus),
                    'dashboardLayout' => json_encode( [
                        'name'   => 'My AtroPIM',
                        'layout' => []
                    ]),
                ])
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();

            // update layout profile for all users
            $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->update($this->getEntityManager()->getConnection()->quoteIdentifier('user'))
                ->set('layout_profile_id', ':id')
                ->where('id is not null')
                ->setParameter('id', $defaultId)
                ->executeStatement();
        } catch (\Throwable $e) {

        }
    }

    /**
     * Generate application ID
     */
    protected function generateAppId(): void
    {
        // generate id
        $appId = substr(md5(md5(Util::generateUniqueHash() . "-atro-salt-") . Util::generateUniqueHash()), 0, 21);

        if (!empty($_SERVER['ATRO_APP_ID'])) {
            $appId = $_SERVER['ATRO_APP_ID'];
        }

        // set to config
        $this->getConfig()->set('appId', $appId);
        $this->getConfig()->save();

        // for statistics
        @file_get_contents("https://packagist.atrocore.com/packages.json?id=$appId");
    }

    /**
     * @return Manager
     */
    private function getModuleManager(): Manager
    {
        return $this->getContainer()->get('moduleManager');
    }
}

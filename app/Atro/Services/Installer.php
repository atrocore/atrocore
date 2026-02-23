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

use Atro\Core\SeederFactory;
use Atro\Core\Templates\Services\HasContainer;
use Atro\Console\AbstractConsole;
use Atro\Core\ModuleManager\Manager;
use Atro\Core\Utils\IdGenerator;
use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Utils\Language;
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
        $license = $this->getFileManager()->getContents('LICENSE.txt');

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
            $this->prepareDatabase();

            // create user
            $user = $this->createSuperAdminUser($params['username'], $params['password']);

            // set installed
            $this->getConfig()->set('isInstalled', true);

            // set reportingEnabled
            $this->getConfig()->set('reportingEnabled', !empty($params['reportingEnabled']));
            $this->getConfig()->save();

            // create anonymous error reports job
            $data = [
                'id'             => '019c2cb5-1746-71ac-bdb2-9f65c3682235',
                'name'           => 'Send anonymous error reports to AtroCore',
                'type'           => 'SendReports',
                'is_active'      => !empty($params['reportingEnabled']),
                'scheduling'     => '*/15 * * * *',
                'created_at'     => date('Y-m-d H:i:s'),
                'modified_at'    => date('Y-m-d H:i:s'),
                'created_by_id'  => $this->getConfig()->get('systemUserId'),
                'modified_by_id' => $this->getConfig()->get('systemUserId'),
            ];

            $conn = $this->getEntityManager()->getConnection();
            $qb = $conn->createQueryBuilder();
            $qb->insert('scheduled_job');
            foreach ($data as $columnName => $value) {
                $qb->setValue($columnName, ":$columnName");
                $qb->setParameter($columnName, $value, Mapper::getParameterType($value));
            }
            try {
                $qb->executeQuery();
            } catch (\Throwable $e) {
            }
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

        return file_exists($config->getConfigPath()) && $config->get('isInstalled');
    }

    /**
     * Check permissions
     *
     * @return bool
     * @throws Exceptions\InternalServerError
     */
    public function checkPermissions(): bool
    {
        $this->getFileManager()->getPermissionUtils()->setMapPermission();

        $error = $this->getFileManager()->getPermissionUtils()->getLastError();

        if (!empty($error)) {
            throw new Exceptions\InternalServerError(is_array($error) ? implode(' ;', $error) : $error);
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
                'phpVersion'  => '8.1',
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
        $connection = $this->getEntityManager()->getDbal();

        // prepare data
        $passwordHash = $this->getPasswordHash()->hash($password);
        $today = (new \DateTime())->format('Y-m-d H:i:s');

        $userId = IdGenerator::uuid();

        $connection->createQueryBuilder()
            ->insert($connection->quoteIdentifier('user'))
            ->setValue('id', ':id')
            ->setValue('actor_id', ':id')
            ->setValue('delegator_id', ':id')
            ->setValue($connection->quoteIdentifier('name'), ':name')
            ->setValue('last_name', ':name')
            ->setValue('user_name', ':userName')
            ->setValue('password', ':password')
            ->setValue('is_admin', ':isAdmin')
            ->setValue('created_at', ':createdAt')
            ->setParameters([
                'id'        => $userId,
                'name'      => 'Admin',
                'userName'  => $username,
                'password'  => $passwordHash,
                'createdAt' => $today
            ])
            ->setParameter('isAdmin', true, ParameterType::BOOLEAN)
            ->executeQuery();

        return $this->getEntityManager()->getEntity('User', $userId);
    }

    /**
     * Create fake system user
     *
     * @throws Exceptions\Error
     */
    protected function createFakeSystemUser(): void
    {
        $systemUser = $this->getEntityManager()->getEntity('User');
        $systemUser->set('id', $this->getConfig()->get('systemUserId'));

        // set system user to container
        $this->getContainer()->setUser($systemUser);
    }

    /**
     * Remove all existing tables and run rebuild
     */
    protected function prepareDatabase(): void
    {
        /** @var array $dbParams */
        $dbParams = $this->getConfig()->get('database');

        $isPgsql = $dbParams['driver'] === 'pdo_pgsql';
        $tableSchema = $isPgsql ? 'public' : $dbParams['dbname'];

        // get existing db tables
        $tables = $this->getContainer()->getDbal()
            ->executeQuery("SELECT table_name FROM information_schema.tables WHERE table_schema='$tableSchema'")
            ->fetchAllAssociative();

        // drop all existing tables if it needs
        if (!empty($tables)) {
            foreach ($tables as $row) {
                $tableName = null;
                foreach (['table_name', 'TABLE_NAME'] as $key) {
                    if (!empty($row[$key])) {
                        $tableName = $this->getEntityManager()->getConnection()->quoteIdentifier($row[$key]);
                        break;
                    }
                }

                if ($tableName === null) {
                    continue;
                }

                if ($isPgsql) {
                    $this->getContainer()->getDbal()
                        ->executeStatement("DROP TABLE IF EXISTS $tableName CASCADE");
                } else {
                    $this->getContainer()->getDbal()
                        ->executeStatement("SET FOREIGN_KEY_CHECKS = 0;DROP TABLE IF EXISTS $tableName;SET FOREIGN_KEY_CHECKS = 1");
                }
            }
        }

        // rebuild database
        $this->getContainer()->getDataManager()->rebuild();
    }

    protected function getLanguage(): Language
    {
        return new Language($this->getContainer());
    }

    protected function afterInstall(): void
    {
        // Generate application ID
        $this->generateAppId();

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

        $seeders = [
            \Atro\Seeders\LocalizationSeeder::class,
            \Atro\Seeders\ScheduledJobSeeder::class,
            \Atro\Seeders\FileStorageSeeder::class,
            \Atro\Seeders\NotificationProfileSeeder::class,
            \Atro\Seeders\HtmlSanitizerSeeder::class,
            \Atro\Seeders\StyleSeeder::class,
            \Atro\Seeders\LayoutProfileSeeder::class,
            \Atro\Seeders\AttributePanelSeeder::class
        ];

        foreach ($seeders as $seeder) {
            $this->getSeederFactory()->create($seeder)->run();
        }

        foreach ($this->getModuleManager()->getModulesList() as $name) {
            try {
                $this->getModuleManager()->getModuleInstallDeleteObject($name)->afterInstall();
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("After Install Module Error: {$e->getMessage()}");
            }
        }

        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " console.php refresh translations >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " console.php regenerate lists >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " console.php regenerate measures >/dev/null");
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " console.php regenerate ui handlers >/dev/null");
    }

    /**
     * Generate application ID
     */
    protected function generateAppId(): void
    {
        // generate id
        $appId = substr(md5(md5(IdGenerator::unsortableId() . "-atro-salt-") . IdGenerator::unsortableId()), 0, 21);

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

    private function getSeederFactory(): SeederFactory
    {
        return $this->getContainer()->get('seederFactory');
    }
}

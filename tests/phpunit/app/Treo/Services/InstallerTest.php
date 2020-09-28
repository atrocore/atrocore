<?php
/**
 * This file is part of EspoCRM and/or TreoCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoCore is EspoCRM-based Open Source application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: https://treolabs.com
 *
 * TreoCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
 * and "TreoCore" word.
 */
declare(strict_types=1);

namespace Treo\Services;

use Treo\Core\Container;
use Treo\Core\Utils\Config;
use Espo\Core\Exceptions;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use PHPUnit\Framework\TestCase;

/**
 * Class InstallerTest
 *
 * @author r.zablodskiy@zinitsolutions.com
 */
class InstallerTest extends TestCase
{
    protected $config = null;
    /**
     * Test is getRequiredsList method exists
     */
    public function testIsGetRequiredsListMethodExists()
    {
        // test 1
        $this->assertTrue(method_exists($this->createPartialMock(Installer::class, []), 'getRequiredsList'));
    }
    /**
     * Test is generateConfig return true
     */
    public function testIsGenerateConfigReturnTrue()
    {
        $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'getDefaults']);
        $this->config
            ->expects($this->any())
            ->method('getConfigPath')
            ->willReturn('data/config.php');
        $this->config
            ->expects($this->any())
            ->method('getDefaults')
            ->willReturn([
                'field1' => 'value1',
                'field2' => 'value2'
            ]);
        $service = $this->createMockService(
            Installer::class,
            [
                'isInstalled',
                'putPhpContents',
                'getDefaultOwner',
                'getDefaultGroup',
                'generateSalt',
                'generateKey',
                'fileExists'
            ]
        );
        $service
            ->expects($this->any())
            ->method('isInstalled')
            ->willReturn(false);
        $service
            ->expects($this->any())
            ->method('getDefaultOwner')
            ->willReturn(5);
        $service
            ->expects($this->any())
            ->method('getDefaultGroup')
            ->willReturn(10);
        $service
            ->expects($this->any())
            ->method('generateSalt')
            ->willReturn('some-salt');
        $service
            ->expects($this->any())
            ->method('generateKey')
            ->willReturn('some-key');
        $service
            ->expects($this->any())
            ->method('putPhpContents')
            ->willReturn(true);
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(false);
        // test 1
        $this->assertTrue($service->generateConfig());
        $service
            ->expects($this->any())
            ->method('getDefaultOwner')
            ->willReturn(null);
        $service
            ->expects($this->any())
            ->method('getDefaultGroup')
            ->willReturn(null);
        // test 2
        $this->assertTrue($service->generateConfig());
    }
    /**
     * Test is generateConfig return false
     */
    public function testIsGenerateConfigReturnFalse()
    {
        $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'getDefaults']);
        $this->config
            ->expects($this->any())
            ->method('getConfigPath')
            ->willReturn('data/config.php');
        $this->config
            ->expects($this->any())
            ->method('getDefaults')
            ->willReturn([
                'field1' => 'value1',
                'field2' => 'value2'
            ]);
        $service = $this->createMockService(
            Installer::class,
            [
                'isInstalled',
                'putPhpContents',
                'getDefaultOwner',
                'getDefaultGroup',
                'generateSalt',
                'generateKey',
                'fileExists'
            ]
        );
        $service
            ->expects($this->any())
            ->method('isInstalled')
            ->willReturn(false);
        $service
            ->expects($this->any())
            ->method('getDefaultOwner')
            ->willReturn(5);
        $service
            ->expects($this->any())
            ->method('getDefaultGroup')
            ->willReturn(10);
        $service
            ->expects($this->any())
            ->method('generateSalt')
            ->willReturn('some-salt');
        $service
            ->expects($this->any())
            ->method('generateKey')
            ->willReturn('some-key');
        $service
            ->expects($this->any())
            ->method('putPhpContents')
            ->willReturn(false);
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(false);
        // test 1
        $this->assertFalse($service->generateConfig());
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(true);
        // test 2
        $this->assertFalse($service->generateConfig());
    }
    /**
     * Test is generateConfig method throw exception while system already installed
     */
    public function testIsGenerateConfigThrowAlreadyInstalledException()
    {
        try {
            $service = $this->createMockService(Installer::class, ['isInstalled', 'translateError']);
            $service
                ->expects($this->any())
                ->method('isInstalled')
                ->willReturn(true);
            $service
                ->expects($this->any())
                ->method('translateError')
                ->willReturn('alreadyInstalled');
            $service->generateConfig();
        } catch (Exceptions\Forbidden $e) {
            // test
            $this->assertEquals('alreadyInstalled', $e->getMessage());
        }
    }
    /**
     * Test is generateConfig method throw exception while empty default config
     */
    public function testIsGenerateConfigThrowEmptyConfigException()
    {
        try {
            $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'getDefaults']);
            $this->config
                ->expects($this->any())
                ->method('getConfigPath')
                ->willReturn('some/config/path.php');
            $this->config
                ->expects($this->any())
                ->method('getDefaults')
                ->willReturn([]);
            $service = $this->createMockService(Installer::class, ['isInstalled']);
            $service
                ->expects($this->any())
                ->method('isInstalled')
                ->willReturn(false);
            $service->generateConfig();
        } catch (Exceptions\Error $e) {
            // test
            $this->assertInstanceOf(Exceptions\Error::class, $e);
        }
    }
    /**
     * Test getTranslations method
     */
    public function testGetTranslationsMethod()
    {
        $service = $this->createMockService(Installer::class, ['getLanguage']);
        $language = $this->createMockService(Language::class, ['get']);
        $service
            ->expects($this->any())
            ->method('getLanguage')
            ->willReturn($language);
        $language
            ->expects($this->any())
            ->method('get')
            ->withConsecutive(['Installer'], ['Global.options.language'])
            ->willReturnOnConsecutiveCalls([
                'fields' => [
                    'host' => 'Host Name',
                    'dbname' => 'Database Name',
                    'user' => 'Database User Name',
                    'username' => 'User Name',
                    'password' => 'Password',
                    'confirmPassword' => 'Confirm Password'
                ]
            ], [
                'de_DE' => 'German (de_DE)',
                'en_GB' => 'English (en_GB)'
            ]);
        // test
        $expects = [
            'fields' => [
                'host' => 'Host Name',
                'dbname' => 'Database Name',
                'user' => 'Database User Name',
                'username' => 'User Name',
                'password' => 'Password',
                'confirmPassword' => 'Confirm Password'
            ],
            'labels' => [
                'languages' => [
                    'de_DE' => 'German (de_DE)',
                    'en_GB' => 'English (en_GB)'
                ]
            ]
        ];
        $this->assertEquals($expects, $service->getTranslations());
    }
    /**
     * Test getLicenseAndLanguages method
     */
    public function testGetLicenseAndLanguagesMethod()
    {
        $this->config = $this->createPartialMock(Config::class, ['get']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->withConsecutive(['languageList'], ['language'])
            ->willReturnOnConsecutiveCalls([
                0 => 'en_US',
                1 => 'de_DE'
            ], 'en_US');
        $service = $this->createMockService(Installer::class, ['getContents']);
        $service
            ->expects($this->any())
            ->method('getContents')
            ->willReturn('Content');
        // test
        $expects = [
            'languageList' => [
                0 => 'en_US',
                1 => 'de_DE'
            ],
            'language' => 'en_US',
            'license' => 'Content'
        ];
        $this->assertEquals($expects, $service->getLicenseAndLanguages());
    }
    /**
     * Test getDefaultDbSettings method
     */
    public function testGetDefaultDbSettingsMethod()
    {
        $this->config = $this->createPartialMock(Config::class, ['getDefaults']);
        $this->config
            ->expects($this->any())
            ->method('getDefaults')
            ->willReturn([
                'database' => array (
                    'driver' => 'pdo_mysql',
                    'host' => 'localhost',
                    'port' => '',
                    'charset' => 'utf8mb4',
                    'dbname' => '',
                    'user' => '',
                    'password' => '',
                ),
                'useCache' => true,
                'recordsPerPage' => 20,
                'recordsPerPageSmall' => 5,
                'applicationName' => 'EspoCRM'
            ]);
        $service = $this->createMockService(Installer::class);
        // test 1
        $expects = [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'port' => '',
            'charset' => 'utf8mb4',
            'dbname' => '',
            'user' => '',
            'password' => '',
        ];
        $this->assertEquals($expects, $service->getDefaultDBSettings());
        $this->config
            ->expects($this->any())
            ->method('getDefaults')
            ->willReturn([
                'useCache' => true,
                'recordsPerPage' => 20,
                'recordsPerPageSmall' => 5,
                'applicationName' => 'EspoCRM'
            ]);
        // test 2
        $this->assertEquals($expects, $service->getDefaultDBSettings());
    }
    /**
     * Test  setLanguage method
     */
    public function testSetLanguageMethod()
    {
        $this->config = $this->createPartialMock(Config::class, ['get', 'set', 'save']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                0 => 'en_US',
                1 => 'de_DE'
            ]);
        $this->config
            ->expects($this->any())
            ->method('set')
            ->willReturn(null);
        $service = $this->createMockService(Installer::class, ['translateError']);
        $service
            ->expects($this->any())
            ->method('translateError')
            ->willReturn('languageNotCorrect');
        // test 1
        $expects = [
            'status' => false,
            'message' => 'languageNotCorrect'
        ];
        $this->assertEquals($expects, $service->setLanguage('en_GB'));
        $this->config = $this->createPartialMock(Config::class, ['get', 'set', 'save']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                0 => 'en_US',
                1 => 'de_DE'
            ]);
        $this->config
            ->expects($this->any())
            ->method('set')
            ->willReturn(null);
        $this->config
            ->expects($this->any())
            ->method('save')
            ->willReturn(false);
        $service = $this->createMockService(Installer::class);
        // test 2
        $expects = [
            'status' => false,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->setLanguage('en_US'));
        $this->config = $this->createPartialMock(Config::class, ['get', 'set', 'save']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                0 => 'en_US',
                1 => 'de_DE'
            ]);
        $this->config
            ->expects($this->any())
            ->method('set')
            ->willReturn(null);
        $this->config
            ->expects($this->any())
            ->method('save')
            ->willReturn(true);
        $service = $this->createMockService(Installer::class);
        // test 3
        $expects = [
            'status' => true,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->setLanguage('en_US'));
    }
    /**
     * Test is setDbSettings return array
     */
    public function testIsSetDbSettingsReturnArray()
    {
        $this->config = $this->createPartialMock(Config::class, ['get', 'set', 'save']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                'driver' => 'pdo_mysql',
                'user' => 'root',
                'password' => 'some-password'
            ]);
        $this->config
            ->expects($this->any())
            ->method('save')
            ->willReturn(true);
        $service = $this->createMockService(
            Installer::class,
            ['prepareDbParams', 'isConnectToDb']
        );
        $service
            ->expects($this->any())
            ->method('prepareDbParams')
            ->willReturn([
                'field' => 'value'
            ]);
        $service
            ->expects($this->any())
            ->method('isConnectToDb')
            ->willReturn(null);
        // test 1
        $expects = [
            'status' => true,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->setDbSettings(['field' => 'value']));
        $this->config = $this->createPartialMock(Config::class, ['get', 'set', 'save']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                'driver' => 'pdo_mysql',
                'user' => 'root',
                'password' => 'some-password'
            ]);
        $this->config
            ->expects($this->any())
            ->method('save')
            ->willReturn(false);
        $service = $this->createMockService(
            Installer::class,
            ['prepareDbParams', 'isConnectToDb']
        );
        $service
            ->expects($this->any())
            ->method('prepareDbParams')
            ->willReturn([
                'field' => 'value'
            ]);
        $service
            ->expects($this->any())
            ->method('isConnectToDb')
            ->willReturn(null);
        // test 2
        $expects = [
            'status' => false,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->setDbSettings(['field' => 'value']));
        $this->config = $this->createPartialMock(Config::class, ['get']);
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn([
                'driver' => 'pdo_mysql',
                'user' => 'root',
                'password' => 'some-password'
            ]);
        $service = $this->createMockService(
            Installer::class,
            ['prepareDbParams', 'isConnectToDb', 'translateError']
        );
        $service
            ->expects($this->any())
            ->method('prepareDbParams')
            ->willReturn([
                'field' => 'value'
            ]);
        $service
            ->expects($this->any())
            ->method('isConnectToDb')
            ->willThrowException(new \Exception());
        $service
            ->expects($this->any())
            ->method('translateError')
            ->willReturn('notCorrectDatabaseConfig');
        // test 3
        $expects = [
            'status' => false,
            'message' => 'notCorrectDatabaseConfig'
        ];
        $this->assertEquals($expects, $service->setDbSettings(['field' => 'value']));
    }
    /**
     * Test createAdmin method
     */
    public function testCreateAdminMethod()
    {
        $this->config = $this->createPartialMock(Config::class, ['set', 'save']);
        $service = $this->createMockService(
            Installer::class,
            ['translateError']
        );
        $service
            ->expects($this->any())
            ->method('translateError')
            ->willReturn('differentPass');
        // test 1
        $expects = [
            'status'  => false,
            'message' => 'differentPass'
        ];
        $data = [
            'username' => 'user1',
            'password' => 'pass',
            'confirmPassword' => 'pass1'
        ];
        $this->assertEquals($expects, $service->createAdmin($data));
        $service = $this->createMockService(
            Installer::class,
            ['createFakeSystemUser', 'createSuperAdminUser', 'dispatch', 'getComposerVersion']
        );
        $user = $this->createMockService(User::class, ['toArray']);
        $service
            ->expects($this->any())
            ->method('createFakeSystemUser')
            ->willReturn(null);
        $service
            ->expects($this->any())
            ->method('createSuperAdminUser')
            ->willReturn($user);
        $service
            ->expects($this->any())
            ->method('dispatch')
            ->willReturn([]);
        $service
            ->expects($this->any())
            ->method('getComposerVersion')
            ->willReturn('1.0.0');
        $user
            ->expects($this->any())
            ->method('toArray')
            ->willReturn([
                'field' => 'value'
            ]);
        // test 2
        $expects = [
            'status'  => true,
            'message' => ''
        ];
        $data = [
            'username' => 'user1',
            'password' => 'pass',
            'confirmPassword' => 'pass'
        ];
        $this->assertEquals($expects, $service->createAdmin($data));
        $service = $this->createMockService(
            Installer::class,
            ['createFakeSystemUser']
        );
        $service
            ->expects($this->any())
            ->method('createFakeSystemUser')
            ->willThrowException(new \Exception('Error'));
        // test 3
        $expects = [
            'status'  => false,
            'message' => 'Error'
        ];
        $this->assertEquals($expects, $service->createAdmin($data));
    }
    /**
     * Test checkDBConnection method
     */
    public function testCheckDbConnectMethod()
    {
        $service = $this->createMockService(Installer::class, ['isConnectToDb', 'prepareDbParams']);
        $data = [
            'host'     => 'host',
            'port'     => 'port',
            'dbname'   => 'dbname',
            'user'     => 'user',
            'password' => 'password'
        ];
        $service
            ->expects($this->once())
            ->method('isConnectToDb')
            ->willReturn(true);
        $service
            ->expects($this->once())
            ->method('prepareDbParams')
            ->willReturn($data);
        // test 1
        $expects = [
            'status' => true,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->checkDbConnect($data));
        $service = $this->createMockService(Installer::class, ['isConnectToDb', 'prepareDbParams']);
        $service
            ->expects($this->once())
            ->method('isConnectToDb')
            ->willReturn(false);
        $service
            ->expects($this->once())
            ->method('prepareDbParams')
            ->willReturn($data);
        // test 2
        $expects = [
            'status' => false,
            'message' => ''
        ];
        $this->assertEquals($expects, $service->checkDbConnect($data));
        $service = $this->createMockService(
            Installer::class,
            ['isConnectToDb', 'prepareDbParams', 'translateError']
        );
        $service
            ->expects($this->once())
            ->method('isConnectToDb')
            ->willThrowException(new \PDOException());
        $service
            ->expects($this->once())
            ->method('prepareDbParams')
            ->willReturn($data);
        $service
            ->expects($this->once())
            ->method('translateError')
            ->willReturn('notCorrectDatabaseConfig');
        // test 3
        $expects = [
            'status' => false,
            'message' => 'notCorrectDatabaseConfig'
        ];
        $this->assertEquals($expects, $service->checkDbConnect($data));
    }
    /**
     * Test isInstalled method
     */
    public function testInstalledMethod()
    {
        $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'get']);
        $this->config
            ->expects($this->any())
            ->method('getConfigPath')
            ->willReturn('/some-path');
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn(false);
        $service = $this->createMockService(Installer::class, ['fileExists']);
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(false);
        // test 1
        $this->assertFalse($service->isInstalled());
        $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'get']);
        $this->config
            ->expects($this->any())
            ->method('getConfigPath')
            ->willReturn('data/config.php');
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn(true);
        $service = $this->createMockService(Installer::class, ['fileExists']);
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(true);
        // test 2
        $this->assertTrue($service->isInstalled());
        $this->config = $this->createPartialMock(Config::class, ['getConfigPath', 'get']);
        $this->config
            ->expects($this->any())
            ->method('getConfigPath')
            ->willReturn('data/config.php');
        $this->config
            ->expects($this->any())
            ->method('get')
            ->willReturn(false);
        $service = $this->createMockService(Installer::class, ['fileExists']);
        $service
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(true);
        // test 3
        $this->assertFalse($service->isInstalled());
    }
    /**
     * Test is checkPermissions return true
     */
    public function testIsCheckPermissionsReturnTrue()
    {
        $service = $this->createMockService(Installer::class, ['setMapPermission', 'getLastError']);
        $service
            ->expects($this->once())
            ->method('setMapPermission')
            ->willReturn(null);
        $service
            ->expects($this->once())
            ->method('getLastError')
            ->willReturn('');
        $this->assertTrue($service->checkPermissions());
    }
    /**
     * Test checkPermissions throw exception
     */
    public function testCheckPermissionsThrowException()
    {
        try {
            $service = $this->createMockService(Installer::class, ['setMapPermission', 'getLastError']);
            $service
                ->expects($this->once())
                ->method('setMapPermission')
                ->willReturn(null);
            $service
                ->expects($this->once())
                ->method('getLastError')
                ->willReturn('Error');
            $service->checkPermissions();
        } catch (Exceptions\InternalServerError $e) {
            $this->assertEquals('Error', $e->getMessage());
        }
    }
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Create mock service
     *
     * @param string $name
     * @param array  $methods
     *
     * @return mixed
     */
    protected function createMockService(string $name, array $methods = [])
    {
        // define path to core app
        if (!defined('CORE_PATH')) {
            define('CORE_PATH', dirname(dirname(dirname(__DIR__))));
        }

        $service = $this->createPartialMock($name, array_merge(['getContainer', 'getConfig'], $methods));
        $service
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($this->getContainer());
        $service
            ->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->getConfig());

        return $service;
    }

    /**
     * @return Container
     */
    protected function getContainer()
    {
        $container = $this->createPartialMock(Container::class, ['getConfig']);
        $container
            ->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->getConfig());

        return $container;
    }
}

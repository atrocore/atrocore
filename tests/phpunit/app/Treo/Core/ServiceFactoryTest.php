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

namespace Treo\Core;

use Espo\Core\Utils\Config;
use PHPUnit\Framework\TestCase;
use Espo\Core\Exceptions\Error;

/**
 * Class ServiceFactoryTest
 *
 * @author r.zablodskiy@zinitsolutions.com
 */
class ServiceFactoryTest extends TestCase
{
    /**
     * Test checkExists method return true
     */
    public function testCheckExistsReturnTrue()
    {
        $service = $this->createMockService(ServiceFactory::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('services');
        $property->setAccessible(true);
        $property->setValue($service, ['class' => 'SomeClass']);

        // test
        $this->assertTrue($service->checkExists('class'));
    }

    /**
     * Test checkExists method return false
     */
    public function testCheckExistsReturnFalse()
    {
        $service = $this->createMockService(ServiceFactory::class);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('services');
        $property->setAccessible(true);
        $property->setValue($service, ['class' => 'SomeClass']);

        // test
        $this->assertFalse($service->checkExists('newClass'));
    }

    /**
     * Test create method throw exception service was not found
     */
    public function testCreateThrowExceptionServiceNotFound()
    {
        $service = $this->createMockService(ServiceFactory::class, ['createByClassName']);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('services');
        $property->setAccessible(true);
        $property->setValue($service, ['class' => 'SomeClass']);

        try {
            $service->create('class1');
        } catch (Error $e) {
            // test
            $expects = "Service 'class1' was not found.";
            $this->assertEquals($expects, $e->getMessage());
        }
    }

    /**
     * Test create method throw exception class not found
     */
    public function testCreateThrowExceptionClassNotFound()
    {
        $service = $this->createMockService(ServiceFactory::class, ['createByClassName']);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('services');
        $property->setAccessible(true);
        $property->setValue($service, ['class' => 'SomeClass']);

        try {
            $service
                ->expects($this->any())
                ->method('createByClassName')
                ->willThrowException(new Error("Class 'SomeClass' does not exist."));

            $service->create('class');
        } catch (Error $e) {
            // test 2
            $expects = "Class 'SomeClass' does not exist.";
            $this->assertEquals($expects, $e->getMessage());
        }
    }

    /**
     * Test create method return object
     */
    public function testCreateMethodReturnObject()
    {
        $service = $this->createMockService(ServiceFactory::class, ['createByClassName']);
        $someClass = $this->getMockBuilder('SomeClass');

        $service
            ->expects($this->any())
            ->method('createByClassName')
            ->willReturn($someClass);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('services');
        $property->setAccessible(true);
        $property->setValue($service, ['class' => 'SomeClass']);

        // test
        $result = $service->create('class');
        $this->assertInternalType('object', $result);
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

    /**
     * @return Config
     */
    protected function getConfig()
    {
        $config = $this->createPartialMock(Config::class, ['set', 'get', 'save']);
        $config
            ->expects($this->any())
            ->method('set')
            ->willReturn(true);
        $config
            ->expects($this->any())
            ->method('get')
            ->willReturn(true);
        $config
            ->expects($this->any())
            ->method('save')
            ->willReturn(true);

        return $config;
    }
}

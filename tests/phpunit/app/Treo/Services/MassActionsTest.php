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

namespace Treo\Services;

use Espo\Core\ORM\Entity;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Config;
use Espo\Services\Record;
use PHPUnit\Framework\TestCase;
use Treo\Core\Container;

/**
 * Class MassActionsTest
 *
 * @author r.zablodskiy@treolabs.com
 */
class MassActionsTest extends TestCase
{
    /**
     * Test massUpdate method
     */
    public function testMassUpdateMethod()
    {
        $service = $this->createMockService(MassActions::class, [
            'getMassActionIds',
            'createMassUpdateJobs',
            'getWebMassUpdateMax',
            'getService'
        ]);
        $recordService = $this->createMockService(Record::class, ['massUpdate']);
        $recordService
            ->expects($this->any())
            ->method('massUpdate')
            ->willReturn([
                'ids' => ['some-id'],
                'count' => 1
            ]);
        $service
            ->expects($this->any())
            ->method('getMassActionIds')
            ->willReturn(['some-id-1', 'some-id-2']);
        $service
            ->expects($this->at(0))
            ->method('getWebMassUpdateMax')
            ->willReturn(1);
        $service
            ->expects($this->any())
            ->method('createMassUpdateJobs')
            ->willReturn(null);
        $service
            ->expects($this->any())
            ->method('getService')
            ->willReturn($recordService);

        // test 1
        $this->assertEquals(
            ['ids' => [], 'count' => 0, 'byQueueManager' => true],
            $service->massUpdate(
                'Entity',
                (object)['attributes' => (object)[]]
            )
        );

        $service
            ->expects($this->at(1))
            ->method('getWebMassUpdateMax')
            ->willReturn(3);

        // test 2
        $this->assertEquals(
            ['ids' => ['some-id'], 'count' => 1],
            $service->massUpdate(
                'Entity',
                (object)['attributes' => (object)[]]
            )
        );
    }

    /**
     * Test massDelete method
     */
    public function testMassDeleteMethod()
    {
        $service = $this->createMockService(MassActions::class, [
            'getMassActionIds',
            'createMassDeleteJobs',
            'getWebMassUpdateMax',
            'getService'
        ]);
        $recordService = $this->createMockService(Record::class, ['massRemove']);
        $recordService
            ->expects($this->any())
            ->method('massRemove')
            ->willReturn([
                'ids' => ['some-id'],
                'count' => 1
            ]);

        $service
            ->expects($this->any())
            ->method('getMassActionIds')
            ->willReturn(['some-id-1', 'some-id-2']);
        $service
            ->expects($this->at(0))
            ->method('getWebMassUpdateMax')
            ->willReturn(1);
        $service
            ->expects($this->any())
            ->method('createMassDeleteJobs')
            ->willReturn(null);
        $service
            ->expects($this->any())
            ->method('getService')
            ->willReturn($recordService);

        // test 1
        $this->assertEquals(
            ['ids' => [], 'count' => 0, 'byQueueManager' => true],
            $service->massDelete(
                'Entity',
                (object)[]
            )
        );

        $service
            ->expects($this->at(1))
            ->method('getWebMassUpdateMax')
            ->willReturn(3);

        // test 2
        $this->assertEquals(
            ['ids' => ['some-id'], 'count' => 1],
            $service->massDelete(
                'Entity',
                (object)[]
            )
        );
    }

    /**
     * Test is addRelation method return false
     */
    public function testIsAddRelationReturnFalse()
    {
        $service = $this->createMockService(MassActions::class, ['getRepository', 'getForeignEntityType']);
        $repository = $this->createMockService(Base::class, ['where', 'find', 'relate']);
        $repository
            ->expects($this->any())
            ->method('where')
            ->willReturn($repository);
        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([]);

        $service
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);
        $service
            ->expects($this->any())
            ->method('getForeignEntityType')
            ->willReturn('ForeignEntityType');

        // test 1
        $this->assertFalse($service->addRelation(['id'], ['foreignId'], 'EntityType', 'link'));

        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([new Entity()]);
        $repository
            ->expects($this->any())
            ->method('relate')
            ->willReturn(false);

        // test 2
        $this->assertFalse($service->addRelation(['id'], ['foreignId'], 'EntityType', 'link'));
    }

    /**
     * Test is addRelation method return true
     */
    public function testIsAddRelationReturnTrue()
    {
        $service = $this->createMockService(MassActions::class, ['getRepository', 'getForeignEntityType']);
        $repository = $this->createMockService(Base::class, ['where', 'find', 'relate']);
        $repository
            ->expects($this->any())
            ->method('where')
            ->willReturn($repository);
        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([new Entity()]);
        $repository
            ->expects($this->any())
            ->method('relate')
            ->willReturn(true);

        $service
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);
        $service
            ->expects($this->any())
            ->method('getForeignEntityType')
            ->willReturn('ForeignEntityType');

        // test
        $this->assertTrue($service->addRelation(['id'], ['foreignId'], 'EntityType', 'link'));
    }

    /**
     * Test is removeRelation method return false
     */
    public function testIsRemoveRelationReturnFalse()
    {
        $service = $this->createMockService(MassActions::class, ['getRepository', 'getForeignEntityType']);
        $repository = $this->createMockService(Base::class, ['where', 'find', 'unrelate']);
        $repository
            ->expects($this->any())
            ->method('where')
            ->willReturn($repository);
        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([]);

        $service
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);
        $service
            ->expects($this->any())
            ->method('getForeignEntityType')
            ->willReturn('ForeignEntityType');

        // test 1
        $this->assertFalse($service->removeRelation(['id'], ['foreignId'], 'EntityType', 'link'));

        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([new Entity()]);
        $repository
            ->expects($this->any())
            ->method('unrelate')
            ->willReturn(false);

        // test 2
        $this->assertFalse($service->removeRelation(['id'], ['foreignId'], 'EntityType', 'link'));
    }

    /**
     * Test is removeRelation method return true
     */
    public function testIsRemoveRelationReturnTrue()
    {
        $service = $this->createMockService(MassActions::class, ['getRepository', 'getForeignEntityType']);
        $repository = $this->createMockService(Base::class, ['where', 'find', 'unrelate']);
        $repository
            ->expects($this->any())
            ->method('where')
            ->willReturn($repository);
        $repository
            ->expects($this->any())
            ->method('find')
            ->willReturn([new Entity()]);
        $repository
            ->expects($this->any())
            ->method('unrelate')
            ->willReturn(true);

        $service
            ->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);
        $service
            ->expects($this->any())
            ->method('getForeignEntityType')
            ->willReturn('ForeignEntityType');

        // test
        $this->assertTrue($service->removeRelation(['id'], ['foreignId'], 'EntityType', 'link'));
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

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

use PHPUnit\Framework\TestCase;

/**
 * Class QueueManagerTest
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class QueueManagerTest extends TestCase
{
    /**
     * Test is run method return true
     */
    public function testIsRunMethodReturnTrue()
    {
        $mock = $this->createPartialMock(QueueManager::class, ['getFileData', 'runJob']);
        $mock
            ->expects($this->any())
            ->method('getFileData')
            ->willReturn(['1', '2']);
        $mock
            ->expects($this->any())
            ->method('runJob')
            ->willReturn(true);

        // test
        $this->assertTrue($mock->run(0));
    }

    /**
     * Test is run method return false
     */
    public function testIsRunMethodReturnFalse()
    {
        $mock = $this->createPartialMock(QueueManager::class, ['getFileData', 'runJob']);
        $mock
            ->expects($this->any())
            ->method('getFileData')
            ->willReturn([]);

        // test 1
        $this->assertFalse($mock->run(0));

        $mock = $this->createPartialMock(QueueManager::class, ['getFileData', 'runJob']);
        $mock
            ->expects($this->any())
            ->method('getFileData')
            ->willReturn(['1']);
        $mock
            ->expects($this->any())
            ->method('runJob')
            ->willReturn(false);

        // test 2
        $this->assertFalse($mock->run(0));
    }

    /**
     * Test is push method return true
     */
    public function testIsPushMethodReturnTrue()
    {
        $mock = $this->createPartialMock(QueueManager::class, ['isService', 'createQueueItem']);
        $mock
            ->expects($this->any())
            ->method('isService')
            ->willReturn(true);
        $mock
            ->expects($this->any())
            ->method('createQueueItem')
            ->willReturn(true);

        // test 1
        $this->assertTrue($mock->push('name', 'service1', ['some-data' => 1]));

        // test 2
        $this->assertTrue($mock->push('name', 'service1'));
    }

    /**
     * Test is push method return false
     */
    public function testIsPushMethodReturnFalse()
    {
        $mock = $this->createPartialMock(QueueManager::class, ['isService', 'createQueueItem']);

        // clonning mock
        $mock1 = clone $mock;

        // test 1
        $mock->expects($this->any())->method('isService')->willReturn(false);
        $this->assertFalse($mock->push('name', 'service1', ['some-data' => 1]));
        $this->assertFalse($mock->push('name', 'service1'));

        // test 2
        $mock1->expects($this->any())->method('isService')->willReturn(true);
        $mock1->expects($this->any())->method('createQueueItem')->willReturn(false);
        $this->assertFalse($mock1->push('name', 'service1', ['some-data' => 1]));
        $this->assertFalse($mock1->push('name', 'service1'));
    }

    /**
     * Test is unsetItem method exists
     */
    public function testIsUnsetItemMethodExists()
    {
        // test
        $this->assertTrue(method_exists($this->createPartialMock(QueueManager::class, []), 'unsetItem'));
    }
}

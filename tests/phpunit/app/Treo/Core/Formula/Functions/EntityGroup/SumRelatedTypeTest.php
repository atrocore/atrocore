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

namespace Treo\Core\Formula\Functions\EntityGroup;

use PHPUnit\Framework\TestCase;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Treo\Core\SelectManagerFactory;
use Espo\Core\SelectManagers\Base as SelectManager;

/**
 * Class SumRelatedTypeTest
 *
 * @author r.zablodskiy@treolabs.com
 */
class SumRelatedTypeTest extends TestCase
{
    /**
     * Test process method
     */
    public function testProcessMethod()
    {
        $mock = $this->createPartialMock(
            SumRelatedType::class,
            ['evaluate', 'getEntity', 'getSelectManagerFactory', 'handleSelectParams', 'query']
        );
        $entity = $this->createPartialMock(Entity::class, ['getRelationParam', 'get']);
        $selectManagerFactory = $this->createPartialMock(SelectManagerFactory::class, ['create']);
        $selectManager = $this->createPartialMock(
            SelectManager::class,
            ['getEmptySelectParams', 'applyFilter', 'addJoin']
        );

        $entity
            ->expects($this->any())
            ->method('getRelationParam')
            ->willReturn('entityType');
        $entity
            ->expects($this->any())
            ->method('get')
            ->withConsecutive(['id'])
            ->willReturnOnConsecutiveCalls('entityId');

        $selectManager
            ->expects($this->any())
            ->method('getEmptySelectParams')
            ->willReturn(['select' => [], 'groupBy' => [], 'whereClause' => []]);

        $selectManagerFactory
            ->expects($this->any())
            ->method('create')
            ->willReturn($selectManager);

        $mock
            ->expects($this->any())
            ->method('evaluate')
            ->withConsecutive(
                [(object)[
                    'type' => 'value',
                    'value' => 'link'
                ]],
                [(object)[
                    'type' => 'value',
                    'value' => 'field'
                ]]
            )
            ->willReturnOnConsecutiveCalls('link', 'field');
        $mock
            ->expects($this->any())
            ->method('getEntity')
            ->willReturn($entity);
        $mock
            ->expects($this->any())
            ->method('getSelectManagerFactory')
            ->willReturn($selectManagerFactory);
        $mock
            ->expects($this->any())
            ->method('query')
            ->willReturn([
                ['SUM:field' => 1]
            ]);

        // test 1
        $data = [
            'type' => 'entity\sumRelated',
            'value' => [
                (object)[
                    'type' => 'value',
                    'value' => 'link'
                ],
                (object)[
                    'type' => 'value',
                    'value' => 'field'
                ]
            ]
        ];

        $this->assertEquals(1, $mock->process((object)$data));

        // test 2
        $mock = $this->createPartialMock(SumRelatedType::class, []);
        try {
            $mock->process((object)[]);
        } catch (\Exception $e) {
            $this->assertInstanceOf(Error::class, $e);
        }

        // test 3
        $mock = $this->createPartialMock(SumRelatedType::class, []);
        try {
            $mock->process((object)['value' => '']);
        } catch (\Exception $e) {
            $this->assertInstanceOf(Error::class, $e);
        }

        // test 4
        $mock = $this->createPartialMock(SumRelatedType::class, []);
        try {
            $mock->process((object)['value' => ['']]);
        } catch (\Exception $e) {
            $this->assertInstanceOf(Error::class, $e);
        }

        // test 5
        $mock = $this->createPartialMock(SumRelatedType::class, ['evaluate']);
        $mock
            ->expects($this->any())
            ->method('evaluate')
            ->willReturn('');
        try {
            $data = [
                'value' => [
                    (object)[
                        'type' => 'value',
                        'value' => 'link'
                    ],
                    (object)[
                        'type' => 'value',
                        'value' => 'field'
                    ]
                ]
            ];
            $mock->process((object)$data);
        } catch (\Exception $e) {
            $this->assertEquals('No link passed to sumRelated function.', $e->getMessage());
        }

        // test 6
        $mock = $this->createPartialMock(SumRelatedType::class, ['evaluate']);
        $mock
            ->expects($this->any())
            ->method('evaluate')
            ->withConsecutive(
                [(object)[
                    'type' => 'value',
                    'value' => 'link'
                ]],
                [(object)[
                    'type' => 'value',
                    'value' => 'field'
                ]]
            )
            ->willReturnOnConsecutiveCalls('link', '');
        try {
            $data = [
                'value' => [
                    (object)[
                        'type' => 'value',
                        'value' => 'link'
                    ],
                    (object)[
                        'type' => 'value',
                        'value' => 'field'
                    ]
                ]
            ];
            $mock->process((object)$data);
        } catch (\Exception $e) {
            $this->assertEquals('No field passed to sumRelated function.', $e->getMessage());
        }
    }
}

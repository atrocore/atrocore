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

namespace Treo\ORM\DB;

/**
 * Class MysqlMapper
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class MysqlMapperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test is updateRelation method exists
     */
    public function testIsUpdateRelationMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'updateRelation'));
    }

    /**
     * Test is massRelate method exists
     */
    public function testIsMassRelateMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'massRelate'));
    }

    /**
     * Test is addRelation method exists
     */
    public function testIsAddRelationMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'addRelation'));
    }

    /**
     * Test is removeRelation method exists
     */
    public function testIsRemoveRelationMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'removeRelation'));
    }

    /**
     * Test is insert method exists
     */
    public function testIsInsertMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'insert'));
    }

    /**
     * Test is update method exists
     */
    public function testIsUpdateMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'update'));
    }

    /**
     * Test is delete method exists
     */
    public function testIsDeleteMethodExists()
    {
        $this->assertTrue(method_exists($this->createPartialMock(MysqlMapper::class, []), 'delete'));
    }
}

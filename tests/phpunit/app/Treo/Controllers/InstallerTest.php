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

namespace Treo\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Class InstallerTest
 *
 * @author r.zablodskiy@treolabs.com
 */
class InstallerTest extends TestCase
{
    /**
     * Test is actionGetTranslations method exists
     */
    public function testIsActionGetTranslationsExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionGetTranslations'));
    }

    /**
     * Test is actionSetLanguage method exists
     */
    public function testIsActionSetLanguageExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionSetLanguage'));
    }

    /**
     * Test is actionGetDefaultDbSettings method exists
     */
    public function testIsActionGetDefaultDbSettingsExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionGetDefaultDbSettings'));
    }

    /**
     * Test is actionSetDbSettings method exists
     */
    public function testIsActionSetDbSettingsExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionSetDbSettings'));
    }

    /**
     * Test is actionCreateAdmin method exists
     */
    public function testIsActionCreateAdminExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionCreateAdmin'));
    }

    /**
     * Test is actionCheckDbConnect method exists
     */
    public function testIsActionCheckDbConnectExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionCheckDbConnect'));
    }

    /**
     * Test is actionGetLicenseAndLanguage method exists
     */
    public function testIsActionGetLicenseAndLanguagesExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionGetLicenseAndLanguages'));
    }

    /**
     * Test is actionGetRequiredsList method exists
     */
    public function testIsActionGetRequiredsListExists()
    {
        $mock = $this->createPartialMock(Installer::class, []);

        // test
        $this->assertTrue(method_exists($mock, 'actionGetRequiredsList'));
    }
}

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
 */

namespace Treo\Migrations;

use Espo\Core\Utils\Json;
use Treo\Console\Cron;
use Treo\Core\Migration\Base;

/**
 * Migration for version 1.1.39
 */
class V1Dot1Dot39 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $composerData = json_decode(file_get_contents('composer.json'), true);

        if (isset($composerData['scripts'])) {
            unset($composerData['scripts']);
        }
        if (isset($composerData['autoload'])) {
            unset($composerData['autoload']);
        }

        file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'index.php');
        copy('vendor/atrocore/core/copy/composer.phar', 'composer.phar');

        if (file_exists('composer-cmd.php')) {
            copy('composer-cmd.php', 'old-composer-cmd.php');
            unlink('composer-cmd.php');
        }

        file_put_contents(Cron::DAEMON_KILLER, '1');
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $composerData = json_decode(file_get_contents('composer.json'), true);

        $composerData['scripts'] = [
            "pre-update-cmd"        => "ComposerCmd::preUpdate",
            "post-update-cmd"       => "ComposerCmd::postUpdate",
            "post-package-install"  => "ComposerCmd::postPackageInstall",
            "post-package-update"   => "ComposerCmd::postPackageUpdate",
            "pre-package-uninstall" => "ComposerCmd::prePackageUninstall"
        ];
        $composerData['autoload']['classmap'] = ["composer-cmd.php"];

        file_put_contents('composer.json', json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        copy('vendor/atrocore/core/copy/.htaccess', '.htaccess');
        copy('vendor/atrocore/core/copy/index.php', 'index.php');
        copy('vendor/atrocore/core/copy/composer.phar', 'composer.phar');

        if (file_exists('old-composer-cmd.php')) {
            copy('old-composer-cmd.php', 'composer-cmd.php');
            unlink('old-composer-cmd.php');
        }

        file_put_contents(Cron::DAEMON_KILLER, '1');
    }
}

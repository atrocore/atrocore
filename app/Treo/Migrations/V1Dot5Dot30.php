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

use Treo\Core\Migration\Base;

/**
 * Migration for version 1.5.30
 */
class V1Dot5Dot30 extends Base
{
    private $defaultExtensions = [
        'aif',
        'cda',
        'mid',
        'midi',
        'mp3',
        'mpa',
        'ogg',
        'wav',
        'wma',
        'wpl',
        '7z',
        'arj',
        'deb',
        'pkg',
        'rar',
        'rpm',
        'tar.gz',
        'z',
        'zip',
        'bin',
        'dmg',
        'iso',
        'toast',
        'vcd',
        'csv',
        'dat',
        'db',
        'dbf',
        'log',
        'mdb',
        'sav',
        'tar',
        'xml',
        'email',
        'eml',
        'emlx',
        'msg',
        'oft',
        'ost',
        'pst',
        'vcf',
        'fnt',
        'fon',
        'otf',
        'ttf',
        'ai',
        'bmp',
        'gif',
        'ico',
        'jpeg',
        'jpg',
        'png',
        'ps',
        'psd',
        'svg',
        'tif',
        'tiff',
        'webp',
        'key',
        'odp',
        'pps',
        'ppt',
        'pptx',
        'ods',
        'xls',
        'xlsm',
        'xlsx',
        '3g2',
        '3gp',
        'avi',
        'flv',
        'h264',
        'm4v',
        'mkv',
        'mov',
        'mp4',
        'mpg',
        'mpeg',
        'rm',
        'swf',
        'vob',
        'webm',
        'wmv',
        'doc',
        'docx',
        'odt',
        'pdf',
        'rtf',
        'tex',
        'txt',
        'wpd'
    ];
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->getConfig()->set('whitelistedExtensions', $this->defaultExtensions);
        $this->getConfig()->save();
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->getConfig()->set('whitelistedExtensions', []);
        $this->getConfig()->save();
    }
}

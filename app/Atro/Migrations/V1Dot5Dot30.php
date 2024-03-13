<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

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

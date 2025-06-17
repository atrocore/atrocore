<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Seeders;

use Atro\Core\Templates\Repositories\ReferenceData;

class LocalizationSeeder extends AbstractSeeder
{
    public function run(): void
    {
        @mkdir(ReferenceData::DIR_PATH);
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Locale.json', json_encode([
            'en_US' => [
                'id'                => 'main',
                'name'              => 'Main',
                'code'              => 'en_US',
                'languageCode'      => 'en_US',
                'dateFormat'        => 'DD.MM.YYYY',
                'timeZone'          => 'UTC',
                'weekStart'         => 'monday',
                'timeFormat'        => 'HH:mm',
                'thousandSeparator' => '.',
                'decimalMark'       => ',',
                'createdAt'         => date('Y-m-d H:i:s')
            ]
        ]));
        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'Language.json', json_encode([
            'en_US' => [
                'id'        => 'main',
                'name'      => 'English',
                'code'      => 'en_US',
                'role'      => 'main',
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ]));
    }
}
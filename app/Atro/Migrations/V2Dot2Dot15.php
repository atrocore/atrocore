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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;

class V2Dot2Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-30 12:00:00');
    }

    public function up(): void
    {
        $fileName = "public/data/publicData.json";

        if (file_exists($fileName)) {
            $data = @json_decode(file_get_contents($fileName), true);
            if (isset($data['qmPaused'])) {
                $data['jmPaused'] = $data['qmPaused'];
                unset($data['qmPaused']);
            }

            file_put_contents($fileName, json_encode($data));
        }
    }
}

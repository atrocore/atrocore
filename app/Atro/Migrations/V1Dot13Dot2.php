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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot13Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-24 08:00:00');
    }

    public function up(): void
    {
        @mkdir('data/reference-data');

        $filePath = 'data/reference-data/Style.json';
        if (!file_exists($filePath)) {
            return;
        }

        $data = @json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as &$item) {
            $item['actionIconColor'] = '#333';
        }
        unset($item);

        file_put_contents($filePath, json_encode($data));
    }
}

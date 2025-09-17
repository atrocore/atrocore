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
use Atro\Core\Utils\Metadata;

class V2Dot1Dot11 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-05 10:00:00');
    }

    public function up(): void
    {
        $fileName = 'data/reference-data/Style.json';

        if (!file_exists($fileName)) {
            return;
        }

        $styles = json_decode(file_get_contents($fileName), true);
        if (!is_array($styles) || empty($styles)) {
            return;
        }

        foreach ($styles as $code => $style) {
            $styles[$code]['toolbarBackgroundColor']  = $style['navigationManuBackgroundColor'] ?? null;
        }

        file_put_contents($fileName, json_encode($styles));
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}

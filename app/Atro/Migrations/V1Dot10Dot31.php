<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot10Dot31 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-07 12:00:00');
    }

    public function up(): void
    {
        @rename('custom/Espo/Custom/Resources/layouts', 'data/layouts');
        @rename('custom/Espo/Custom/Resources/metadata', 'data/metadata');

        self::removeDir('custom');

        $this->updateComposer('atrocore/core', '^1.10.31');
    }

    public static function removeDir(string $dir): void
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (self::scanDir($dir) as $object) {
                if (is_dir($dir . "/" . $object)) {
                    self::removeDir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    public static function scanDir(string $dir): array
    {
        $result = [];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }
}

<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot3Dot8 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-12 12:00:00');
    }

    public function up(): void
    {
        self::removeDir('data/migrations');

        $data = @json_decode(file_get_contents('composer.json'), true);
        if (!empty($data['autoload']['psr-0'][''])) {
            $psr0 = [];
            foreach ($data['autoload']['psr-0'][''] as $item) {
                if ($item !== 'data/migrations/') {
                    $psr0[] = $item;
                }
            }

            $data['autoload']['psr-0'][''] = $psr0;
            file_put_contents('composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

    public static function removeDir(string $dir): void
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (self::scanDir($dir) as $object) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                    self::removeDir($dir . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}

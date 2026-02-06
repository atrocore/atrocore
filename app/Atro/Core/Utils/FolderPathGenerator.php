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

declare(strict_types=1);

namespace Atro\Core\Utils;

class FolderPathGenerator
{
    public static function generate(
        string $rootPath = '',
        bool $withDateFolders = false,
        int $shardDepth = 3,
        int $shardLength = 5,
        int $idBytes = 12
    ): string {
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);

        // Unique ID
        $id = bin2hex(random_bytes($idBytes));

        // Build shard folders
        $shards = [];
        for ($i = 0; $i < $shardDepth; $i++) {
            $shards[] = substr($id, $i * $shardLength, $shardLength);
        }

        $basePath = $withDateFolders ? [date('Y'), date('m')] : [];

        if ($rootPath !== '') {
            array_unshift($basePath, $rootPath);
        }

        return implode(DIRECTORY_SEPARATOR, array_merge($basePath, $shards, [$id]));
    }
}

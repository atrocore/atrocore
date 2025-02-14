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

namespace Atro\Services;

use Atro\Core\Utils\Util;

class App extends \Espo\Services\App
{
    public const LISTENING_DIR = 'public/listening';

    public function startEntityListening(string $entityName, string $entityId): array
    {
        $dir = self::LISTENING_DIR;
        $fileName = "{$dir}/{$entityName}_{$entityId}.json";

        if (file_exists($fileName)) {
            $timestamp = file_get_contents($fileName);
        } else {
            $timestamp = time();

            Util::createDir($dir);
            file_put_contents($fileName, $timestamp);
        }

        return [
            'timestamp' => $timestamp,
            'endpoint'  => $fileName
        ];
    }
}

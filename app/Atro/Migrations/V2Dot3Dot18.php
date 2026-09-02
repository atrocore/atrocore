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
use Atro\Services\Variable;

/**
 * Moves user-defined variables out of data/config.php into their own file.
 *
 * In the config each variable occupied a top-level key next to the system
 * parameters, so a variable could collide with a real setting - and every
 * mechanism exposing the config risked exposing the variables with it.
 */
class V2Dot3Dot18 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-02 12:00:00');
    }

    public function up(): void
    {
        $config = $this->getConfig();

        $keys = $config->get('variables', []);
        if (empty($keys) || !is_array($keys)) {
            return;
        }

        $variables = Variable::loadAll();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $variables)) {
                $variables[$key] = $config->get($key, '');
            }

            $config->remove($key);
        }

        $config->remove('variables');
        $config->save();

        file_put_contents(
            Variable::FILE_PATH,
            json_encode($variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

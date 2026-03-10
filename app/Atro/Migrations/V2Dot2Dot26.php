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
use Atro\Core\Utils\RegexUtil;

class V2Dot2Dot26 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-09 12:00:00');
    }

    public function up(): void
    {
        // Strip // delimiters from fileNameRegexPattern and passwordRegexPattern in config
        foreach (['fileNameRegexPattern', 'passwordRegexPattern'] as $key) {
            $value = $this->getConfig()->get($key);
            if (!empty($value)) {
                $stripped = RegexUtil::stripDelimiters($value);
                if ($stripped !== $value) {
                    $this->getConfig()->set($key, $stripped);
                }
            }
        }
        $this->getConfig()->save();

        // Strip // delimiters from pattern field definitions stored in custom entity metadata
        $customPath = 'data/metadata/entityDefs';
        if (!is_dir($customPath)) {
            return;
        }

        foreach (glob($customPath . '/*.json') as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }

            $changed = false;
            foreach ($data['fields'] ?? [] as $field => $defs) {
                if (!empty($defs['pattern'])) {
                    $stripped = RegexUtil::stripDelimiters($defs['pattern']);
                    if ($stripped !== $defs['pattern']) {
                        $data['fields'][$field]['pattern'] = $stripped;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
    }
}

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

class V2Dot2Dot29 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-17 12:00:00');
    }

    public function up(): void
    {
        // Strip // delimiters from fileNameRegexPattern and passwordRegexPattern in config
        foreach (['fileNameRegexPattern', 'passwordRegexPattern'] as $key) {
            $value = $this->getConfig()->get($key);
            if (!empty($value)) {
                $stripped = $this->stripDelimiters($value);
                if ($stripped !== $value) {
                    $this->getConfig()->set($key, $stripped);
                    $hasChanged = true;
                }
            }
        }

        if(!empty($hasChanged)) {
            $this->getConfig()->save();
        }

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
                    $stripped = $this->stripDelimiters($defs['pattern']);
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

    public function stripDelimiters(string $pattern): string
    {
        if (preg_match('/^\/(.*)\/([gmixsuAJD]*)$/', $pattern, $matches)) {
            return $matches[1];
        }
        return $pattern;
    }
}

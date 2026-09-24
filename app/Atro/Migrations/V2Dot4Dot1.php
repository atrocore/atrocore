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

class V2Dot4Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-14 13:00:00');
    }

    public function up(): void
    {
        // isCode was renamed to isSlug. It's a field-definition metadata flag, not a real DB column,
        // so module-shipped defaults are handled by plain source edits - only this installation's own
        // data/metadata/entityDefs overrides (outside any module repo) need to be carried forward.
        $this->renameIsCodeToIsSlugInDataMetadata();
    }

    protected function renameIsCodeToIsSlugInDataMetadata(): void
    {
        foreach (glob('data/metadata/entityDefs/*.json') ?: [] as $file) {
            $data = @json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || empty($data['fields']) || !is_array($data['fields'])) {
                continue;
            }

            $changed = false;
            foreach ($data['fields'] as $field => &$defs) {
                if (is_array($defs) && array_key_exists('isCode', $defs)) {
                    $defs['isSlug'] = $defs['isCode'];
                    unset($defs['isCode']);
                    $changed = true;
                }
            }
            unset($defs);

            if ($changed) {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}

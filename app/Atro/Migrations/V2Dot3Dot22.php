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

class V2Dot3Dot22 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-15 10:00:00');
    }

    public function up(): void
    {
        // auditableEnabled was renamed to isAuditableRelation. It's a field-definition metadata flag,
        // not a real DB column, so module-shipped defaults are handled by plain source edits - only this
        // installation's own data/metadata/entityDefs overrides need to be carried forward.
        // The flag is only ever read for linkMultiple fields, so on any other field type it is dropped.
        $this->renameAuditableEnabledInDataMetadata();
    }

    protected function renameAuditableEnabledInDataMetadata(): void
    {
        foreach (glob('data/metadata/entityDefs/*.json') ?: [] as $file) {
            $data = @json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || empty($data['fields']) || !is_array($data['fields'])) {
                continue;
            }

            $changed = false;
            foreach ($data['fields'] as $field => &$defs) {
                if (!is_array($defs) || !array_key_exists('auditableEnabled', $defs)) {
                    continue;
                }

                if (($defs['type'] ?? null) === 'linkMultiple') {
                    $defs['isAuditableRelation'] = $defs['auditableEnabled'];
                }

                unset($defs['auditableEnabled']);
                $changed = true;
            }
            unset($defs);

            if ($changed) {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}

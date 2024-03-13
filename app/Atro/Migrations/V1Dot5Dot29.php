<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot5Dot29 extends Base
{
    public function up(): void
    {
        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        $options = $this
            ->getPDO()
            ->query("SELECT `value`, `attribute`, `entity_type` FROM `array_value` WHERE deleted=0 GROUP BY `value`, `attribute`, `entity_type` ORDER BY `entity_type`, `attribute`")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($options as $option) {
            if (empty($option['attribute']) || empty($option['entity_type'])) {
                continue;
            }

            $defs = $metadata->get(['entityDefs', $option['entity_type'], 'fields', $option['attribute']]);

            if (isset($defs['type']) && in_array($defs['type'], ['enum', 'multiEnum'])) {
                if (!isset($defs['optionsIds']) || !isset($defs['options'])) {
                    continue;
                }

                $key = array_search($option['value'], $defs['options']);
                if ($key !== false) {
                    $this->exec("UPDATE `array_value` SET `value`='{$defs['optionsIds'][$key]}' WHERE deleted=0 AND `value`='{$option['value']}'");
                }
            }
        }
    }

    public function down(): void
    {
    }

    public function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}

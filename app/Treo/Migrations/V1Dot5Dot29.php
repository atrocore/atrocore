<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Treo\Migrations;

use Treo\Core\Migration\Base;

class V1Dot5Dot29 extends Base
{
    public function up(): void
    {
        /** @var \Espo\Core\Utils\Metadata $metadata */
        $metadata = (new \Espo\Core\Application())->getContainer()->get('metadata');

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

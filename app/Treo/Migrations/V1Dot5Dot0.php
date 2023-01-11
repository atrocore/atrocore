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

class V1Dot5Dot0 extends Base
{
    public function up(): void
    {
//        /** @var \Espo\Core\Utils\Metadata $metadata */
//        $metadata = (new \Espo\Core\Application())->getContainer()->get('metadata');
//
//        foreach ($metadata->get('entityDefs') as $entityType => $entityDefs) {
//            if (empty($entityDefs['fields'])) {
//                continue;
//            }
//            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
//                if (empty($fieldDefs['type'])) {
//                    continue;
//                }
//                if (in_array($fieldDefs['type'], ['enum', 'multiEnum'])) {
//                    if (isset($fieldDefs['view']) || !isset($fieldDefs['options'])) {
//                        continue;
//                    }
//
//                    if (!isset($fieldDefs['optionsIds']) || count($fieldDefs['options']) !== count($fieldDefs['optionsIds'])) {
//                        echo '<pre>';
//                        print_r($entityType);
//                        print_r($field);
//                        print_r($fieldDefs);
//                        die();
//                    }
//                }
//            }
//        }

//        $this->execute("ALTER TABLE user ADD type VARCHAR(255) DEFAULT 'Token' COLLATE `utf8mb4_unicode_ci`");
//        $this->execute("UPDATE user SET type='Token' WHERE 1");
    }

    public function down(): void
    {
//        $this->execute("ALTER TABLE user DROP type");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}

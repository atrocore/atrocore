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

namespace Espo\Core\Utils\FieldManager\Types;

use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

/**
 * Class Asset
 */
class Asset extends Type
{
    /**
     * @inheritDoc
     */
    public function prepareSqlUniqueData(Entity $entity, string $field): array
    {
        $table = Util::toUnderScore($entity->getEntityType());
        $dbField = Util::toUnderScore($field);

        $result = [
            'select' => '',
            'joins' => '',
            'where' => ''
        ];

        if (!empty($asset = $entity->get($field))) {
            $result['select'] = "attachment.md5 AS `$field`";
            $result['joins'] = "JOIN attachment ON attachment.id = $table.{$dbField}_id AND attachment.deleted = 0";
            $result['where'] = "attachment.md5 = '" . $asset->get('md5') . "'";
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function checkEquals(Entity $entity, string $field, array $data): bool
    {
        return $entity->get($field)->get('md5') == $data[$field];
    }
}
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

namespace Espo\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Util;

class Variable extends Base
{
    public function findEntities(array $params): array
    {
        $variables = $this->getConfig()->get('variables', []);

        return [
            'total' => count($variables),
            'list'  => $variables
        ];
    }

    public function createEntity(\stdClass $attachment): array
    {
        $variables = $this->getConfig()->get('variables', []);

        $name = $attachment->name;

        // validate name
        if ($name === 'variables' || $this->getConfig()->has($name) || in_array($name, array_column($this->getConfig()->get('variables', []), 'name'))) {
            throw new BadRequest("Such name '{$name}' is already using.");
        }

        $variable = [
            'id'    => Util::generateId(),
            'name'  => $name,
            'type'  => $attachment->type ?? 'text',
            'value' => $attachment->value ?? null,
        ];

        $variables[] = $variable;

        $this->getConfig()->set('variables', $variables);
        $this->getConfig()->save();

        return $variable;
    }

    public function updateEntity(string $id, \stdClass $data): array
    {
        $variables = $this->getConfig()->get('variables', []);

        $index = null;
        foreach ($variables as $k => $row) {
            if ($row['id'] === $id) {
                $index = $k;
            }
        }

        if (empty($index)) {
            throw new NotFound();
        }

        // validate name
        if (property_exists($data, 'name')) {
            if ($data->name === 'variables' || $this->getConfig()->has($data->name)) {
                throw new BadRequest("Such name '{$data->name}' is already using.");
            }
            foreach ($variables as $row) {
                if ($row['name'] === $data->name && $row['id'] !== $id) {
                    throw new BadRequest("Such name '{$data->name}' is already using.");
                }
            }
            $variables[$index]['name'] = $data->name;
        }

        if (property_exists($data, 'type')) {
            $variables[$index]['type'] = $data->type;
        }

        if (property_exists($data, 'value')) {
            $variables[$index]['value'] = $data->value;
        }

        $this->getConfig()->set('variables', $variables);
        $this->getConfig()->save();

        return $variables[$index];
    }

    public function readEntity(string $id): array
    {
        foreach ($this->getConfig()->get('variables', []) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        throw new NotFound();
    }

    public function deleteEntity(string $id): bool
    {
        $found = false;

        $variables = [];
        foreach ($this->getConfig()->get('variables', []) as $row) {
            if ($row['id'] !== $id) {
                $variables[] = $row;
            } else {
                $found = true;
            }
        }

        if (!$found) {
            throw new NotFound();
        }

        $this->getConfig()->set('variables', $variables);
        $this->getConfig()->save();

        return true;
    }
}

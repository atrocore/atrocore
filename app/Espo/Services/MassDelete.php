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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Espo\Services;

class MassDelete extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (empty($data['entityType'])) {
            return false;
        }

        $ids = [];
        if (array_key_exists('ids', $data) && !empty($data['ids']) && is_array($data['ids'])) {
            $ids = $data['ids'];
        }

        if (array_key_exists('where', $data)) {
            $selectManager = $this->getContainer()->get('selectManagerFactory')->create($data['entityType']);
            $selectParams = $selectManager->getSelectParams(['where' => $data['where']], true, true);
            $this->getEntityManager()->getRepository($data['entityType'])->handleSelectParams($selectParams);

            $query = $this
                ->getEntityManager()
                ->getQuery()
                ->createSelectQuery($data['entityType'], array_merge($selectParams, ['select' => ['id']]));

            $ids = $this
                ->getEntityManager()
                ->getPDO()
                ->query($query)
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        if (empty($ids)) {
            return false;
        }

        $service = $this->getContainer()->get('serviceFactory')->create($data['entityType']);

        foreach ($ids as $id) {
            try {
                $service->deleteEntity($id);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Delete {$data['entityType']} '$id' failed: {$e->getMessage()}");
            }
        }

        return true;
    }
}

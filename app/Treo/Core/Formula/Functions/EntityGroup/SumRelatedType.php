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

namespace Treo\Core\Formula\Functions\EntityGroup;

use Espo\Core\Exceptions\Error;
use Espo\Core\Formula\Functions\EntityGroup\SumRelatedType as EspoSumRelatedType;
use Espo\Core\SelectManagerFactory;
use Espo\ORM\EntityManager;

/**
 * Class SumRelatedType
 */
class SumRelatedType extends EspoSumRelatedType
{
    /**
     * @inheritdoc
     */
    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) {
            throw new Error();
        }

        if (!is_array($item->value)) {
            throw new Error();
        }

        if (count($item->value) < 2) {
            throw new Error();
        }

        $link = $this->evaluate($item->value[0]);

        if (empty($link)) {
            throw new Error("No link passed to sumRelated function.");
        }

        $field = $this->evaluate($item->value[1]);

        if (empty($field)) {
            throw new Error("No field passed to sumRelated function.");
        }

        $filter = null;
        if (count($item->value) > 2) {
            $filter = $this->evaluate($item->value[2]);
        }

        $entity = $this->getEntity();

        $foreignEntityType = $entity->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new Error();
        }

        $foreignSelectManager = $this->getSelectManagerFactory()->create($foreignEntityType);

        $foreignLink = $entity->getRelationParam($link, 'foreign');

        if (empty($foreignLink)) {
            throw new Error("No foreign link for link {$link}.");
        }

        $selectParams = $foreignSelectManager->getEmptySelectParams();

        if ($filter) {
            $foreignSelectManager->applyFilter($filter, $selectParams);
        }

        $selectParams['select'] = [[$foreignLink . '.id', 'foreignId'], 'SUM:' . $field];

        $foreignSelectManager->addJoin($foreignLink, $selectParams);

        $selectParams['groupBy'] = [$foreignLink . '.id'];
        // @todo treoinject. Espo bug fix
        $selectParams['whereClause'][] = [$foreignLink . '.id' => $entity->get('id')];

        $this->handleSelectParams($foreignEntityType, $selectParams);

        $rowList = $this->query($foreignEntityType, $selectParams);

        if (empty($rowList)) {
            return 0;
        }

        return $rowList[0]['SUM:' . $field];
    }

    /**
     * Get entity manager
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    /**
     * Get select manager factory
     *
     * @return SelectManagerFactory
     */
    protected function getSelectManagerFactory(): SelectManagerFactory
    {
        return $this->getInjection('selectManagerFactory');
    }

    /**
     * Handle select params
     *
     * @param string $foreignEntityType
     * @param array $selectParams
     */
    protected function handleSelectParams(string $foreignEntityType, array $selectParams)
    {
        $this
            ->getEntityManager()
            ->getRepository($foreignEntityType)
            ->handleSelectParams($selectParams);
    }

    /**
     * Execute query
     *
     * @param string $foreignEntityType
     * @param array $selectParams
     *
     * @return array
     */
    protected function query(string $foreignEntityType, array $selectParams): array
    {
        $sql = $this->getEntityManager()->getQuery()->createSelectQuery($foreignEntityType, $selectParams);

        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($sql);
        $sth->execute();
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
}

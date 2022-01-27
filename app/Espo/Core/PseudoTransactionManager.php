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

namespace Espo\Core;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use PDO;
use Treo\Core\ServiceFactory;

class PseudoTransactionManager
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function push(string $entityType, string $entityId, string $action, array $input): void
    {
        $id = Util::generateId();
        $entityType = $this->getPDO()->quote($entityType);
        $entityId = $this->getPDO()->quote($entityId);
        $action = $this->getPDO()->quote($action);
        $input = Json::encode($input);
        $createdById = $this->getUser()->get('id');

        $this
            ->getPDO()
            ->exec(
                "INSERT INTO `pseudo_transaction` (id,entity_type,entity_id,action,input_data,created_by_id) VALUES ('$id',$entityType,$entityId,$action,'$input','$createdById')"
            );
    }

    public function run(string $entityType, string $entityId): void
    {
        $entityType = $this->getPDO()->quote($entityType);
        $entityId = $this->getPDO()->quote($entityId);

        $jobs = $this
            ->getPDO()
            ->query("SELECT * FROM `pseudo_transaction` WHERE deleted=0 AND entity_type=$entityType AND entity_id=$entityId ORDER BY sort_order ASC")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jobs as $job) {
            try {
                $service = $this->getServiceFactory()->create($job['entity_type']);
                $user = $this->getEntityManager()->getEntity('User', $job['created_by_id']);

                $this->container->setUser($user);
                $this->getEntityManager()->setUser($user);
                if (!empty($user->get('portalId'))) {
                    $this->container->setPortal($user->get('portal'));
                }

                $inputData = @json_decode($job['input_data'], true);
                $service->{$job['action']}(...$inputData);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("PseudoTransaction job failed: {$e->getMessage()}");
            }

            $this->getPDO()->exec("DELETE FROM `pseudo_transaction` WHERE id='{$job['id']}'");
        }
    }

    protected function getPDO(): PDO
    {
        return $this->container->get('pdo');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}

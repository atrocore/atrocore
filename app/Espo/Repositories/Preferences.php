<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

namespace Espo\Repositories;

use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Doctrine\DBAL\Connection;

class Preferences extends \Espo\Core\ORM\Repository
{
    protected $defaultAttributeListFromSettings = ['followCreatedEntities'];

    protected $data = array();

    protected $entityType = 'Preferences';

    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'fileManager',
            'metadata',
            'config',
            'entityManager',
            'connection'
        ]);
    }

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    protected function getEntityManger()
    {
        return $this->getInjection('entityManager');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getConnection(): Connection
    {
        return $this->getInjection('connection');
    }

    public function get($id = null)
    {
        if ($id) {
            $entity = $this->entityFactory->create('Preferences');
            $entity->id = $id;
            if (empty($this->data[$id])) {
                $row = $this->getConnection()->createQueryBuilder()
                    ->select('id, data')
                    ->from('preferences')
                    ->where('id = :id')
                    ->setParameter('id', $id)
                    ->fetchAssociative();

                $data = null;
                if (!empty($row)){
                    $data = Json::decode($row['data']);
                    $data = get_object_vars($data);
                }

                if ($data) {
                    $this->data[$id] = $data;
                } else {
                    $fields = $this->getMetadata()->get('entityDefs.Preferences.fields');
                    $defaults = array();

                    $dashboardLayout = $this->getConfig()->get('dashboardLayout');
                    $dashletsOptions = null;
                    if (!$dashboardLayout) {
                        $dashboardLayout = $this->getMetadata()->get('app.defaultDashboardLayouts.Standard');
                        $dashletsOptions = $this->getMetadata()->get('app.defaultDashboardOptions.Standard');
                    }

                    if ($dashletsOptions === null) {
                        $dashletsOptions = $this->getConfig()->get('dashletsOptions', (object) []);
                    }

                    $defaults['dashboardLayout'] = $dashboardLayout;
                    $defaults['dashletsOptions'] = $dashletsOptions;

                    foreach ($fields as $field => $d) {
                        if (array_key_exists('default', $d)) {
                            $defaults[$field] = $d['default'];
                        }
                    }
                    foreach ($this->defaultAttributeListFromSettings as $attr) {
                        $defaults[$attr] = $this->getConfig()->get($attr);
                    }

                    $this->data[$id] = $defaults;
                    $entity->set($defaults);
                }
            }

            $entity->set($this->data[$id]);

            $localeId = null;
            if (!empty($entity->get('locale'))) {
                $localeId = $entity->get('locale');
            }

            if (!empty($localeId)) {
                $locales = $this->getConfig()->get('locales', []);
                if (isset($locales[$localeId])) {
                    foreach ($locales[$localeId] as $name => $value) {
                        $entity->set($name, $value);
                    }
                }
            }

            $this->fetchAutoFollowEntityTypeList($entity);

            $entity->setAsFetched($this->data[$id]);

            return $entity;
        }
    }

    protected function fetchAutoFollowEntityTypeList(Entity $entity)
    {
        $id = $entity->id;

        $rows = $this->getConnection()->createQueryBuilder()
            ->select('entity_type')
            ->from('autofollow')
            ->where('user_id = :id')
            ->setParameter('id', $id)
            ->orderBy('entity_type', 'ASC')
            ->fetchAllAssociative();

        $autoFollowEntityTypeList = [];
        foreach ($rows as $row) {
            $autoFollowEntityTypeList[] = $row['entity_type'];
        }
        $this->data[$id]['autoFollowEntityTypeList'] = $autoFollowEntityTypeList;
        $entity->set('autoFollowEntityTypeList', $autoFollowEntityTypeList);
    }

    protected function storeAutoFollowEntityTypeList(Entity $entity)
    {
        $id = $entity->id;

        $was = $entity->getFetched('autoFollowEntityTypeList');
        $became = $entity->get('autoFollowEntityTypeList');

        if (!is_array($was)) {
            $was = [];
        }
        if (!is_array($became)) {
            $became = [];
        }

        if ($was == $became) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete('autofollow')
            ->where('user_id = :userId')
            ->setParameter('userId', $id)
            ->executeQuery();

        $scopes = $this->getMetadata()->get('scopes');
        foreach ($became as $entityType) {
            if (isset($scopes[$entityType]) && !empty($scopes[$entityType]['stream'])) {
                $connection->createQueryBuilder()
                    ->insert('autofollow')
                    ->setValue('user_id', ':userId')
                    ->setValue('entity_type', ':entityType')
                    ->setParameter('userId', $id)
                    ->setParameter('entityType', $entityType)
                    ->executeQuery();
            }
        }
    }

    public function save(Entity $entity, array $options = array())
    {
        if (!$entity->id) return;

        $this->data[$entity->id] = $entity->toArray();

        $fields = $this->getMetadata()->get('entityDefs.Preferences.fields');

        $data = array();
        foreach ($this->data[$entity->id] as $field => $value) {
            if (empty($fields[$field]['notStorable'])) {
                $data[$field] = $value;
            }
        }

        $data['locale'] = null;
        if (!empty($entity->get('localeId'))) {
            $data['locale'] = $entity->get('localeId');
        } elseif (!empty($entity->get('locale'))) {
            $data['locale'] = $entity->get('locale');
        }

        $dataString = Json::encode($data, \JSON_PRETTY_PRINT);

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('preferences'))
            ->where('id = :id')
            ->setParameter('id', $entity->id)
            ->executeQuery();

        $connection->createQueryBuilder()
            ->insert($connection->quoteIdentifier('preferences'))
            ->setValue('id', ':id')
            ->setValue('data', ':data')
            ->setParameter('id', $entity->id)
            ->setParameter('data', $dataString)
            ->executeQuery();

        $user = $this->getEntityManger()->getEntity('User', $entity->id);
        if ($user) {
            $this->storeAutoFollowEntityTypeList($entity);
        }

        return $entity;
    }

    public function deleteFromDb($id)
    {
        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->delete($connection->quoteIdentifier('preferences'))
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery();
    }

    public function remove(Entity $entity, array $options = array())
    {
        if (!$entity->id) return;
        $this->deleteFromDb($entity->id);
    }

    public function resetToDefaults($userId)
    {
        $this->deleteFromDb($userId);
        if (isset($this->data[$userId])) {
            unset($this->data[$userId]);
        }
        if ($entity = $this->get($userId)) {
            return $entity->toArray();
        }
    }

    public function hasLocale(string $locale): bool
    {
        $connection = $this->getEntityManager()->getConnection();

        $count = $connection->createQueryBuilder()
            ->select('p.id')
            ->from($connection->quoteIdentifier('preferences'), 'p')
            ->where("p.data LIKE :val1 OR p.data LIKE :val2")
            ->setParameter('val1', "%\"locale\": \"$locale\"%")
            ->setParameter('val2', "%\"locale\":\"$locale\"%")
            ->fetchAssociative();

        return !empty($count);
    }

    public function find(array $params)
    {
    }

    public function findOne(array $params)
    {
    }

    public function getAll()
    {
    }

    public function count(array $params)
    {
    }
}

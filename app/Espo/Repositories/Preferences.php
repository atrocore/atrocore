<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;
use Espo\Core\Utils\Json;

class Preferences extends \Espo\Core\ORM\Repository
{
    protected $defaultAttributeListFromSettings = [
        'decimalMark',
        'thousandSeparator',
        'exportDelimiter',
        'followCreatedEntities'
    ];

    protected $data = array();

    protected $entityType = 'Preferences';

    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'fileManager',
            'metadata',
            'config',
            'entityManager'
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

    public function get($id = null)
    {
        if ($id) {
            $entity = $this->entityFactory->create('Preferences');
            $entity->id = $id;
            if (empty($this->data[$id])) {
                $pdo = $this->getEntityManger()->getPDO();
                $sql = "SELECT `id`, `data` FROM `preferences` WHERE id = ".$pdo->quote($id);
                $ps = $pdo->query($sql);

                $data = null;

                $sth = $pdo->prepare($sql);
                $sth->execute();

                while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
                    $data = Json::decode($row['data']);
                    $data = get_object_vars($data);
                    break;
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

            $this->fetchAutoFollowEntityTypeList($entity);

            $entity->setAsFetched($this->data[$id]);

            return $entity;
        }
    }

    protected function fetchAutoFollowEntityTypeList(Entity $entity)
    {
        $id = $entity->id;

        $autoFollowEntityTypeList = [];
        $pdo = $this->getEntityManger()->getPDO();
        $sql = "
            SELECT `entity_type` AS 'entityType' FROM `autofollow`
            WHERE `user_id` = ".$pdo->quote($id)."
            ORDER BY `entity_type`
        ";
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $rows = $sth->fetchAll();
        foreach ($rows as $row) {
            $autoFollowEntityTypeList[] = $row['entityType'];
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
        $pdo = $this->getEntityManger()->getPDO();
        $sql = "DELETE FROM autofollow WHERE user_id = ".$pdo->quote($id)."";
        $pdo->query($sql);

        $scopes = $this->getMetadata()->get('scopes');
        foreach ($became as $entityType) {
            if (isset($scopes[$entityType]) && !empty($scopes[$entityType]['stream'])) {
                $sql = "
                    INSERT INTO autofollow (user_id, entity_type)
                    VALUES (".$pdo->quote($id).", ".$pdo->quote($entityType).")
                ";
                $pdo->query($sql);
            }
        }
    }

    public function save(Entity $entity, array $options = array())
    {
        if (!$entity->id) return;

        $this->data[$entity->id] = $entity->toArray();

        $fields = $fields = $this->getMetadata()->get('entityDefs.Preferences.fields');

        $data = array();
        foreach ($this->data[$entity->id] as $field => $value) {
            if (empty($fields[$field]['notStorable'])) {
                $data[$field] = $value;
            }
        }

        $dataString = Json::encode($data, \JSON_PRETTY_PRINT);

        $pdo = $this->getEntityManger()->getPDO();

        $sql = "
            INSERT INTO `preferences` (`id`, `data`) VALUES (".$pdo->quote($entity->id).", ".$pdo->quote($dataString).")
            ON DUPLICATE KEY UPDATE `data` = ".$pdo->quote($dataString)."
        ";

        $pdo->query($sql);

        $user = $this->getEntityManger()->getEntity('User', $entity->id);
        if ($user && !$user->get('isPortalUser')) {
            $this->storeAutoFollowEntityTypeList($entity);
        }

        return $entity;
    }

    public function deleteFromDb($id)
    {
        $pdo = $this->getEntityManger()->getPDO();
        $sql = "DELETE  FROM `preferences` WHERE `id` = " . $pdo->quote($id);
        $ps = $pdo->query($sql);
    }

    public function remove(Entity $entity, array $options = array())
    {
        if (!$entity->id) return;
        $this->deleteFromDb($entity->id);
        if (isset($this->data[$userId])) {
            unset($this->data[$userId]);
        }
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

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\Acl;
use Espo\ORM\Entity;

class SavedSearch extends Base
{
    const CACHE_NAME = 'saved_search';

    public function getEntitiesFromCache()
    {
        $cachedData = $this->getDataManager()->getCacheData(self::CACHE_NAME);
        if ($cachedData === null) {
            $cachedData = [];
            foreach ($this->find() as $entity) {
                if(!$this->getMetadata()->get(['scopes', $entity->get('entityType')])) {
                    continue;
                }
                $this->cleanDeletedFieldsFromFilterData($entity);
                if(!empty($entity->get('data'))) {
                    $cachedData[] = $entity->toArray();
                }
            }
            $this->getDataManager()->setCacheData(self::CACHE_NAME, $cachedData);
        }
        return $cachedData;
    }

    public function cleanDeletedFieldsFromFilterData(Entity $entity): void
    {
        // Clean filter to remove all removed fields
        $data = json_decode(json_encode($entity->get('data')), true);
        if(array_key_exists('condition', $data)) {
          $this->cleanQueryBuilderData($data, $entity->get('entityType'));
        }else{
            $searchEntity = $this->getEntityManager()->getEntity($entity->get('entityType'));
            foreach ($data as $filterField => $value) {
                $name = explode('-', $filterField)[0];
                if ($name === 'id') {
                    continue;
                }
                if (!$searchEntity->hasField($name)) {
                    unset($data[$filterField]);
                }
            }
        }

        $entity->set('data', $data);
    }

    private function cleanQueryBuilderData(array &$data, string $scope): void
    {
        if(!empty($data['rules'])) {
            $searchEntity = $this->getEntityManager()->getEntity($scope);
            foreach($data['rules'] as $key => $rule) {
                if(!empty($rule['field'])
                    && !$searchEntity->hasField($rule['field']) && $rule['field'] !== 'id'
                    && !str_starts_with($rule['field'], 'attr_')
                ){
                    unset($data['rules'][$key]);
                }

                if(!empty($rule['rules'])) {
                    $this->cleanQueryBuilderData($rule, $scope);
                }
            }
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $currentUserId = $this->getEntityManager()->getUser()->id;

        if($entity->isNew()) {
            $entity->set('userId', $currentUserId);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);
        $this->deleteCacheFile();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);
        $this->deleteCacheFile();
    }

    protected function deleteCacheFile(): void
    {
        $file = DataManager::CACHE_DIR_PATH . '/' . self::CACHE_NAME . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function getAcl(): Acl
    {
        return $this->getInjection('acl');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
        $this->addDependency('acl');
    }
}

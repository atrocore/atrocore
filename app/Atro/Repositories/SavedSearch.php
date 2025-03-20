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
use Atro\Core\Templates\Repositories\Base;
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
        foreach ($data as $filterField => $value) {
            $name = explode('-', $filterField)[0];
            if ($name === 'id') {
                continue;
            }
            if (!$this->getMetadata()->get(['entityDefs', $entity->get('entityType'), 'fields', $name])) {
                unset($data[$filterField]);
            }
        }
        $entity->set('data', $data);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $entity->set('userId', $this->getEntityManager()->getUser()->id);
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

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }
}

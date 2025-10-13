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

    public static  function getWhereFromWhereData($whereData, $entityManager): ?array
    {
        if(empty($whereData['savedFilters'])
            && empty($whereData['bool'])
            && empty($whereData['queryBuilder'])
            && empty($whereData['advanced'])
            && empty($whereData['textFilter'])
        ) {
            return null;
        }

        $where = [];

        if (!empty($whereData['textFilter'])) {
            $where[] = [
                'type' => 'textFilter',
                'value' => $whereData['textFilter']
            ];
        }

        if (!empty($whereData['bool'])) {
            $o = [
                'type' => 'bool',
                'value' => [],
                'data' => []
            ];
            foreach ($whereData['bool'] as $name => $isSet) {
                if ($isSet) {
                    $o['value'][] = $name;
                    $boolData = $whereData['boolFilterData'] ?? null;
                    if (!empty($boolData[$name])) {
                        $o['data'][$name] = $boolData[$name];
                    }
                }
            }
            if (!empty($o['value'])) {
                $where[] = $o;
            }
        }


        if (!empty($whereData['savedFilters'])) {
            $savedFilters = $entityManager
                ->getRepository('SavedSearch')
                ->where(['id' => array_column($whereData['savedFilters'], 'id')])
                ->find();
            foreach ($savedFilters as $item) {
                if (isset($item->get('data')?->condition)) {
                    $where[] = json_decode(json_encode($item->get('data')), true);
                } else {
                    // support for savedSearch using old advanced filters
                    $where = array_merge($where, self::getAdvancedWhere($item['data']));
                }
            }
        }

        if (!empty($whereData['queryBuilder']['condition']) && !empty($whereData['queryBuilderApplied'])) {
            $where[] = $whereData['queryBuilder'];
        }


        return $where;
    }

    public function getEntitiesFromCache()
    {
        $cachedData = $this->getDataManager()->getCacheData(self::CACHE_NAME);
        if ($cachedData === null) {
            $cachedData = [];
            foreach ($this->find() as $entity) {
                if(!$this->getMetadata()->get(['scopes', $entity->get('entityType')])) {
                    $this->deleteFromDb($entity->get('id'));
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

    static protected function getAdvancedWhere(array $data): array
    {
        $groups = [];
        foreach ($data as $name => $defs) {
            if (empty($defs)) {
                continue;
            }
            $nameParts = explode('-', $name);
            $clearedName = $nameParts[0];

            $part = self::getAdvancedWherePart($clearedName, $defs);
            $groups[$clearedName][] = $part;
        }

        $finalPart = [];
        foreach ($groups as $name => $groupItems) {
            if (count($groupItems) > 1) {
                $group = [
                    'type' => 'or',
                    'value' => $groupItems
                ];
            } else {
                $group = $groupItems[0];
            }
            $finalPart[] = $group;
        }

        return $finalPart;
    }

    static  protected  function getAdvancedWherePart(string $name, array $defs): array
    {
        $attribute = $name;

        if (isset($defs['where'])) {
            return $defs['where'];
        }

        $type = $defs['type'] ?? null;

        if ($type === 'or' || $type === 'and') {
            $a = [];
            $value = $defs['value'] ?? [];
            foreach ($value as $n => $v) {
                $mergedDefs = array_merge($v, [
                    'subQuery' => $defs['subQuery'] ?? [],
                    'fieldParams' => [
                        'isAttribute' => $defs['isAttribute'] ?? ($defs['fieldParams']['isAttribute'] ?? null)
                    ]
                ]);
                $a[] = self::getAdvancedWherePart($n, $mergedDefs);
            }
            return [
                'type' => $type,
                'value' => $a
            ];
        }
        if (isset($defs['field'])) {
            $attribute = $defs['field'];
        }
        if (isset($defs['attribute'])) {
            $attribute = $defs['attribute'];
        }

        if (isset($defs['dateTime'])) {
            return [
                'type' => $type,
                'attribute' => $attribute,
                'isAttribute' => $defs['fieldParams']['isAttribute'] ?? null,
                'subQuery' => $defs['subQuery'] ?? [],
                'value' => $defs['value'] ?? null,
                'dateTime' => true,
            ];
        } else {
            $value = $defs['value'] ?? null;
            return [
                'type' => $type,
                'attribute' => $attribute,
                'isAttribute' => $defs['fieldParams']['isAttribute'] ?? null,
                'subQuery' => $defs['subQuery'] ?? [],
                'value' => $value
            ];
        }
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

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

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class UiHandler extends ReferenceData
{
    public function find(array $params = [])
    {
        $items = $this->getAllItems($params);
        $items = array_values($items);

        // filter by name
        foreach ($params['whereClause'] ?? [] as $row) {
            if (!empty($row['name*'])) {
                $search = str_replace('%', '', $row['name*']);
                $items = array_filter($items, function ($item) use ($search) {
                    return isset($item['name']) && preg_match('/^' . preg_quote($search, '/') . '/i', $item['name']);
                });
            }
        }

        // text filter
        if (!empty($params['whereClause'][0]['OR'])) {
            $filtered = [];
            $optionNames = [];
            foreach ($params['whereClause'][0]['OR'] as $k => $v) {
                $field = str_replace('*', '', $k);
                $search = str_replace('%', '', $v);

                foreach ($items as $item) {
                    if ($field === 'fields') {
                        $value = is_string($item[$field]) ? @json_decode($item[$field], true) : $item[$field];
                        if (!isset($filtered[$item['code']]) && is_array($value) && !empty($value)) {
                            $targetFieldsTranslated = array_map(
                                fn($field) => $this->translate($field, 'fields', $item['entityType'] ?? ''),
                                $value
                            );
                            if (strpos(join(', ', $targetFieldsTranslated), $search) !== false) {
                                $filtered[$item['code']] = $item;
                            }
                        }
                    }else if ($field === 'type') {
                        if (!isset($filtered[$item['code']]) && is_string($item[$field])) {
                            if(!empty($optionNames[$item[$field]])) {
                                $optionName = $optionNames[$item[$field]];
                            }else{
                                foreach ($this->getMetadata()->get(['app', 'extensibleEnumOptions'], []) as $option) {
                                    $optionName = null;
                                    if(!empty($option['id']) && $option['id'] === $item[$field]) {
                                        $optionNames[$option['id']] = $optionName = $option['name'];
                                        break;
                                    }
                                }
                            }

                            if (!empty($optionName) && strpos($optionName, $search) !== false) {
                                $filtered[$item['code']] = $item;
                            }
                        }
                    }else if (!isset($filtered[$item['code']]) && is_string($item[$field]) && strpos($item[$field], $search) !== false) {
                        $filtered[$item['code']] = $item;
                    }
                }
            }

            $items = array_values($filtered);
        }

        // sort data
        if (!empty($params['orderBy'])) {
            usort($items, function ($a, $b) use ($params) {
                $field = $params['orderBy'];
                if (strtolower($params['order']) === 'desc') {
                    return $b[$field] <=> $a[$field];
                } else {
                    return $a[$field] <=> $b[$field];
                }
            });
        }

        // limit data
        if (isset($params['limit']) && isset($params['offset'])) {
            $prepared = [];
            foreach ($items as $k => $item) {
                if ($k >= $params['offset'] && count($prepared) < $params['limit']) {
                    $prepared[] = $item;
                }
            }
            $items = $prepared;
        }

        $collection = new EntityCollection($items, $this->entityName, $this->entityFactory);
        $collection->setAsFetched();

        return $collection;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew()) {
            foreach (['type', 'isActive', 'entityType', 'fields', 'conditionsType', 'conditions'] as $field) {
                if ($entity->isAttributeChanged($field)) {
                    $this->validateSystemHandler($entity);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->refreshCache();

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateSystemHandler($entity);

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->refreshCache();

        parent::afterRemove($entity, $options);
    }

    public function validateSystemHandler(Entity $entity): void
    {
        if (!empty($entity->get('system'))) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('systemHandler', 'exceptions', 'UiHandler'), $entity->get('name')));
        }
    }

    public function refreshCache(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('memoryStorage');
    }
}

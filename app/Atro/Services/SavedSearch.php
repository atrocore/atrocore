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

namespace Atro\Services;

use Atro\Core\DataManager;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Acl;
use Espo\ORM\Entity;

class SavedSearch extends Base
{
    const CACHE_NAME = 'saved_search';

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

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

    public function findEntities($params)
    {
        if (!$this->getDataManager()->isUseCache(self::CACHE_NAME)) {
            $params['where'][] = [
                "type" => "or",
                "value" => [
                    [
                        "type" => "equals",
                        "attribute" => "userId",
                        "value" => $this->getUser()->id
                    ],
                    [
                        "type" => "equals",
                        "attribute" => "isPublic",
                        "value" => true
                    ]
                ]
            ];
            return parent::findEntities($params);
        } else {
            $cachedData = $this->getDataManager()->getCacheData(self::CACHE_NAME);
            if ($cachedData === null) {
                $cachedData = $this->getRepository()->find()->toArray();
                $this->getDataManager()->setCacheData(self::CACHE_NAME, $cachedData);
            }
            if ($this->getAcl()->checkReadOnlyOwn($this->entityType)) {
                $entities = array_filter($cachedData, function ($item) use ($params) {
                    return $item['userId'] === $this->getUser()->id && $item['entityType'] === $params['scope'];
                });
            } else {
                $entities = array_filter($cachedData, function ($item) use ($params) {
                    return $item['entityType'] === $params['scope']
                        && ($item['userId'] === $this->getUser()->id || $item['isPublic'] === true);
                });
            }

            return [
                "list" => array_values($entities),
            ];
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

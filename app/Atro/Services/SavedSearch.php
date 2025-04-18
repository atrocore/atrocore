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
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->getRepository()->cleanDeletedFieldsFromFilterData($entity);
    }

    public function findEntities($params)
    {
        foreach ($params['where'] as $where) {
            if(!empty($where['attribute']) && $where['attribute'] === 'entityType' && !empty($where['value'])) {
                $scope = $where['value'];
            }
        }

        if (!$this->getDataManager()->isUseCache(\Atro\Repositories\SavedSearch::CACHE_NAME)) {
            $params['where'][] = [
                "type" => "or",
                "value" => [
                    [
                        "type" => "equals",
                        "attribute" => "userId",
                        "value" => $this->getUser()->id
                    ],
                    [
                        "type" => "isTrue",
                        "attribute" => "isPublic"
                    ]
                ]
            ];
            return parent::findEntities($params);
        } else {
            $cachedData = $this->getRepository()->getEntitiesFromCache();
            if ($this->getAcl()->checkReadOnlyOwn($this->entityType)) {
                $entities = array_filter($cachedData, function ($item) use ($scope) {
                    $bool = $item['userId'] === $this->getUser()->id;
                    if(!empty($scope)) {
                        $bool = $bool && $item['entityType'] === $scope;
                    }
                    return  $bool;
                });
            } else {
                $entities = array_filter($cachedData, function ($item) use ($scope) {
                    $bool = $item['userId'] === $this->getUser()->id || $item['isPublic'] === true;
                    if(!empty($scope)) {
                        $bool = $bool && $item['entityType'] === $scope;
                    }
                    return  $bool;
                });
            }

            return [
                "list" => array_values($entities),
            ];
        }
    }


    protected function getAcl(): Acl
    {
        return $this->getInjection('acl');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('acl');
        $this->addDependency('dataManager');
    }
}

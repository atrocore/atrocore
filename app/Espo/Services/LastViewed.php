<?php

namespace Espo\Services;

use \Espo\ORM\Entity;

class LastViewed extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependency('serviceFactory');
        $this->addDependency('metadata');
    }

    public function get()
    {
        $entityManager = $this->getInjection('entityManager');

        $maxSize = $this->getConfig()->get('lastViewedCount', 20);

        $actionHistoryRecordService = $this->getInjection('serviceFactory')->create('ActionHistoryRecord');

        $scopes = $this->getInjection('metadata')->get('scopes');

        $targetTypeList = array_filter(array_keys($scopes), function ($item) use ($scopes) {
            return !empty($scopes[$item]['object']);
        });

        $collection = $this->getEntityManager()->getRepository('ActionHistoryRecord')->where(array(
            'userId' => $this->getUser()->id,
            'action' => 'read',
            'targetType' => $targetTypeList
        ))->order(3, true)->limit(0, $maxSize)->select([
            'targetId', 'targetType', 'MAX:number'
        ])->groupBy([
            'targetId', 'targetType'
        ])->find();

        foreach ($collection as $i => $entity) {
            $actionHistoryRecordService->loadParentNameFields($entity);
            $entity->id = $i;
        }

        return array(
            'total' => count($collection),
            'collection' => $collection
        );
    }
}


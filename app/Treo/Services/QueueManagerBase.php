<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;

/**
 * Class QueueManagerBase
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class QueueManagerBase extends AbstractService implements QueueManagerServiceInterface
{
    /**
     * @param array $data
     *
     * @return bool
     */
    public function run(array $data = []): bool
    {
        return true;
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getPendingStatusActions(Entity $entity): array
    {
        return [
            [
                'type' => 'cancel',
                'data' => []
            ]
        ];
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getRunningStatusActions(Entity $entity): array
    {
        return [
            [
                'type' => 'cancel',
                'data' => []
            ]
        ];
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getFailedStatusActions(Entity $entity): array
    {
        return [
            [
                'type' => 'close',
                'data' => []
            ]
        ];
    }

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getSuccessStatusActions(Entity $entity): array
    {
        return [
            [
                'type' => 'close',
                'data' => []
            ]
        ];
    }
}

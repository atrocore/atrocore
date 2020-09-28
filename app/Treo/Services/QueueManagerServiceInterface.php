<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;

/**
 * Interface QueueManagerServiceInterface
 *
 * @author r.ratsun@zinitsolutions.com
 */
interface QueueManagerServiceInterface
{
    /**
     * @param array $data
     *
     * @return bool
     */
    public function run(array $data = []): bool;

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getPendingStatusActions(Entity $entity): array;

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getRunningStatusActions(Entity $entity): array;

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getFailedStatusActions(Entity $entity): array;

    /**
     * @param Entity $entity
     *
     * @return array
     */
    public function getSuccessStatusActions(Entity $entity): array;
}

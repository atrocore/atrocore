<?php

declare(strict_types=1);

namespace Treo\Services;

/**
 * Class QueueManagerMassDelete
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class QueueManagerMassDelete extends QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        // prepare result
        $result = false;

        // call mass remove method
        if (isset($data["entityType"]) && !empty($data["ids"]) && is_array($data["ids"])) {
            $this->massRemove($data["entityType"], ["ids" => $data["ids"]]);

            // prepare result
            $result = true;
        }

        return $result;
    }

    /**
     * @param string $entityType
     * @param array  $ids
     *
     * @return array
     */
    protected function massRemove(string $entityType, array $data): array
    {
        return $this
            ->getContainer()
            ->get('serviceFactory')
            ->create($entityType)
            ->massRemove($data);
    }
}

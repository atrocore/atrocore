<?php

declare(strict_types=1);

namespace Treo\Services;

/**
 * Class QueueManagerMassUpdate
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class QueueManagerMassUpdate extends QueueManagerBase
{
    /**
     * @inheritdoc
     */
    public function run(array $data = []): bool
    {
        // prepare result
        $result = false;

        // call mass remove method
        if (isset($data["entityType"])
            && !empty($data["attributes"])
            && is_array($data["attributes"])
            && !empty($data["ids"])
            && is_array($data["ids"])) {
            $this->massUpdate($data["entityType"], $data["attributes"], ["ids" => $data["ids"]]);

            // prepare result
            $result = true;
        }

        return $result;
    }

    /**
     * @param string $entityType
     * @param array  $attributes
     * @param array  $data
     *
     * @return array
     */
    protected function massUpdate(string $entityType, array $attributes, array $data): array
    {
        return $this
            ->getContainer()
            ->get('serviceFactory')
            ->create($entityType)
            ->massUpdate($attributes, $data);
    }
}

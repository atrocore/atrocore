<?php

declare(strict_types=1);

namespace Treo\Core\Utils\Metadata;

use Espo\Core\Utils\Metadata\OrmMetadata as Base;

/**
 * Class OrmMetadata
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class OrmMetadata extends Base
{
    /**
     * @inheritDoc
     */
    public function getData($reload = false)
    {
        return $this->unsetLinkName(parent::getData($reload));
    }

    /**
     * Unset link field name if it needs
     *
     * @param array $data
     *
     * @return array
     */
    protected function unsetLinkName(array $data): array
    {
        /** @var array $entityDefs */
        $entityDefs = $this->metadata->get('entityDefs', []);

        foreach ($entityDefs as $scope => $rows) {
            if (!isset($rows['links'])) {
                continue 1;
            }

            foreach ($rows['links'] as $link => $settings) {
                if (isset($settings['type'])) {
                    if ($settings['type'] == 'belongsTo'
                        && !isset($entityDefs[$settings['entity']]['fields']['name'])
                        && isset($data[$scope]['fields'][$link . 'Name'])) {
                        unset($data[$scope]['fields'][$link . 'Name']);
                    }
                    if ($settings['type'] == 'hasMany'
                        && !isset($entityDefs[$settings['entity']]['fields']['name'])
                        && isset($data[$scope]['fields'][$link . 'Names'])) {
                        unset($data[$scope]['fields'][$link . 'Names']);
                    }
                }
            }
        }

        return $data;
    }
}

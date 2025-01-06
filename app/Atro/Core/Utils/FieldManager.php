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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Espo\Core\Utils\Metadata;

class FieldManager
{
    protected Container $container;
    protected Metadata $metadata;
    protected array $fieldByTypeListCache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->metadata = $container->get('metadata');
    }

    public function getActualAttributeList(string $scope, string $name): array
    {
        return $this->getAttributeListByType($scope, $name, 'actual');
    }

    public function getNotActualAttributeList(string $scope, string $name): array
    {
        return $this->getAttributeListByType($scope, $name, 'notActual');
    }

    public function getAttributeList(string $scope, string $name): array
    {
        return array_merge(
            $this->getActualAttributeList($scope, $name),
            $this->getNotActualAttributeList($scope, $name)
        );
    }

    public function getFieldByTypeList(string $scope, string $type): array
    {
        if (!array_key_exists($scope, $this->fieldByTypeListCache)) {
            $this->fieldByTypeListCache[$scope] = [];
        }

        if (!array_key_exists($type, $this->fieldByTypeListCache[$scope])) {
            $fieldDefs = $this->metadata->get(['entityDefs', $scope, 'fields'], []);
            $list = [];
            foreach ($fieldDefs as $field => $defs) {
                if (isset($defs['type']) && $defs['type'] === $type) {
                    $list[] = $field;
                }
            }
            $this->fieldByTypeListCache[$scope][$type] = $list;
        }

        return $this->fieldByTypeListCache[$scope][$type];
    }

    protected function getAttributeListByType(string $scope, string $name, string $type): array
    {
        $fieldType = $this->metadata->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->metadata->get('fields.' . $fieldType);
        if (!$defs) {
            return [];
        }

        if (is_object($defs)) {
            $defs = get_object_vars($defs);
        }

        $fieldList = [];
        if (isset($defs[$type . 'Fields'])) {
            $list = $defs[$type . 'Fields'];
            $naming = 'suffix';
            if (isset($defs['naming'])) {
                $naming = $defs['naming'];
            }
            if ($naming == 'prefix') {
                foreach ($list as $f) {
                    $fieldList[] = $f . ucfirst($name);
                }
            } else {
                foreach ($list as $f) {
                    $fieldList[] = $name . ucfirst($f);
                }
            }
        } else {
            if ($type == 'actual') {
                $fieldList[] = $name;
            }
        }

        return $fieldList;
    }
}

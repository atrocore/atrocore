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

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getActualAttributeList(string $scope, string $name): array
    {
        return $this->getAttributeListByType($scope, $name, 'actual');
    }

    public function getNotActualAttributeList(string $scope, string $name): array
    {
        return $this->getAttributeListByType($scope, $name, 'notActual');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getAttributeListByType(string $scope, string $name, string $type): array
    {
        $fieldType = $this->getMetadata()->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->getMetadata()->get('fields.' . $fieldType);
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
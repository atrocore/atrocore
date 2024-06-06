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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class Connection extends Base
{
    protected $entityType = "Connection";

    public function get($name, $params = [])
    {
        if (!empty($this->getAttributeParam($name, 'dataField'))) {
            return $this->getDataField($name);
        }

        return parent::get($name, $params);
    }

    public function setDataField(string $name, $value): void
    {
        $data = [];
        if (!empty($this->get('data'))) {
            $data = Json::decode(Json::encode($this->get('data')), true);
        }

        $data[$name] = $value;

        $this->valuesContainer[$name] = $value;
        $this->set('data', $data);
    }

    public function populateDefaults()
    {
        parent::populateDefaults();
        parent::setFieldValue('data', null);
    }

    public function getDataField(string $name)
    {
        $data = $this->getDataFields();

        if (!isset($data[$name])) {
            return null;
        }

        return $data[$name];
    }

    public function getDataFields(): array
    {
        if (!empty($data = $this->get('data'))) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data) && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    protected function setFieldValue(string $field, $value): void
    {
        if (!empty($this->getAttributeParam($field, 'dataField'))) {
            $this->setDataField($field, $value);
        }

        parent::setFieldValue($field, $value);
    }
}

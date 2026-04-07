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

use Atro\Core\Templates\Services\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class ExtensibleEnumOption extends Base
{
    public function updateEntity(string $id, \stdClass $data): bool
    {
        if (property_exists($data, '_id') && property_exists($data, '_sortedIds') && property_exists($data, '_scope') && !empty($data->_sortedIds)) {
            $this->getRepository()->updateSortOrder($data->_sortedIds);
            return true;
        }

        return parent::updateEntity($id, $data);
    }

}

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
use Espo\ORM\Entity;

class ExtensibleEnumOption extends Base
{
    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_id') && property_exists($data, '_sortedIds') && property_exists($data, '_scope') && !empty($data->_sortedIds)) {
            $this->getEntityManager()->getRepository('ExtensibleEnumExtensibleEnumOption')->updateSortOrder($data->_id, $data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($this->getMemoryStorage()->get('exportJobId')) && empty($this->getMemoryStorage()->get('importJobId'))  && $entity->get('listMultilingual') === null) {
           $hasMultilingual = $this->getEntityManager()
                ->getConnection()
                ->createQueryBuilder()
                ->from('extensible_enum','ee')
                ->join('ee','extensible_enum_extensible_enum_option','eeeeo', 'ee.id=eeeeo.extensible_enum_id')
                ->select('ee.id')
                ->where('eeeeo.extensible_enum_option_id=:id')
                ->where('ee.multilingual=:true')
                ->setParameter('id', $entity->get('id'), Mapper::getParameterType($entity->get('id')))
                ->setParameter('true',true, Mapper::getParameterType(true))
                ->fetchOne();

               $entity->set('listMultilingual', !empty($hasMultilingual));

        }
    }
}

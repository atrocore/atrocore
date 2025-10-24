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

namespace Atro\Repositories;

use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;


class SelectionRecord extends Base
{
   protected function beforeSave(Entity $entity, array $options = [])
   {
       $select = ['id'];

       if ($this->getMetadata()->get(['entityDefs', $entity->get('entityType'), 'fields', 'name'])) {
           $select[] = 'name';
       }

       $record = $this->getEntityManager()->getRepository($entity->get('entityType'))
           ->select($select)
           ->where(['id' => $entity->get('entityId')])
           ->findOne();

       if(empty($record)) {
           throw new Error("Selection record not found");
       }

       $entity->set('name', $record->get('name') ?? $record->get('id'));

       parent::beforeSave($entity, $options);
   }
}

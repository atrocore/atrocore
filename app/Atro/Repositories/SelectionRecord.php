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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

       if($entity->isNew() && !empty($entity->get('selectionsIds'))) {
           $id = md5($entity->get('selectionsIds')[0] . $entity->get('entityId') .$entity->get('entityType'));
           $entity->set('id', $id);
       }

       parent::beforeSave($entity, $options);
   }

   public function save(Entity $entity, array $options = [])
   {
       try {
           return parent::save($entity, $options);
       }catch (NotUnique $e) {
           throw new BadRequest("Selection record already exists");
       }
   }
}

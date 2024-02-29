<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Espo\ORM\Entity;

class ExtensibleEnumExtensibleEnumOption extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && $entity->get('sorting') === null) {
            $entity->set('sorting', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }

        parent::beforeSave($entity, $options);
    }



    public function updateSortOrder(string $extensibleEnumId, array $extensibleEnumOptionIds): void
    {
        $collection = $this->where(['extensibleEnumId' => $extensibleEnumId, 'extensibleEnumOptionId' => $extensibleEnumOptionIds])->find();
        if (empty($collection[0])) {
            return;
        }

        foreach ($extensibleEnumOptionIds as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('extensibleEnumOptionId') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }

}

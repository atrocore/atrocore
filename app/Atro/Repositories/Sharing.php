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

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Sharing extends Base
{
    /**
     * Counts an opening of the link. The counter is updated directly, so an opening does not change the record.
     */
    public function incrementUsed(string $id): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update('sharing')
            ->set('used', 'COALESCE(used, 0) + 1')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeStatement();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->isNew() && empty($entity->get('name'))) {
            $file = $this->getEntityManager()->getRepository('File')->get($entity->get('fileId'));
            $entity->set('name', date('ymd') . ' / ' . $file->get('name'));
        }

        $this->syncRestriction($entity, 'limitUsage', 'allowedUsage');
        $this->syncRestriction($entity, 'expires', 'validTill');
    }

    /**
     * A restriction applies only when its flag is on.
     */
    protected function syncRestriction(Entity $entity, string $flagField, string $valueField): void
    {
        if (empty($entity->get($flagField))) {
            $entity->set($valueField, null);
        }
    }
}

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

namespace Atro\Core\Templates\Services;

use Atro\Services\Record;
use Espo\ORM\Entity;

class Base extends Record
{
    public function putAclMeta(Entity $entity): void
    {
        parent::putAclMeta($entity);

        // set permissions for additional actions of derivative entity
        if (!empty($masterEntity = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.primaryEntityId")) && !empty($entity->get('masterRecordId'))) {
            $entity->setMetaPermission('updateMasterRecord', false);
            if ($this->getUser()->isAdmin()) {
                $entity->setMetaPermission('updateMasterRecord', true);
            } else {
                $masterRecord = $this->getEntityManager()->getRepository($masterEntity)->get($entity->get('masterRecordId'));
                if ($masterRecord) {
                    $entity->setMetaPermission('updateMasterRecord', $this->getAcl()->check($masterRecord, 'edit'));
                }
            }
        }
    }
}

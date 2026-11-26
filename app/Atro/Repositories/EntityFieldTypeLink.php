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
use Espo\ORM\Entity as OrmEntity;

/**
 * The default value of a link field is an existing record of the foreign entity.
 */
class EntityFieldTypeLink extends EntityField
{
    protected function validateDefaultValue(OrmEntity $entity): void
    {
        parent::validateDefaultValue($entity);

        $defaultId = $entity->get('defaultId');
        if (empty($defaultId) || empty($foreignEntity = $entity->get('foreignEntityId'))) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('defaultId') && !$entity->isAttributeChanged('foreignEntityId')) {
            return;
        }

        if (empty($this->getEntityManager()->getRepository($foreignEntity)->get($defaultId))) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('defaultRecordIsNotFound', 'exceptions', 'EntityField'), $defaultId));
        }
    }
}

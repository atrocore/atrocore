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
 * The default "From" of a range field is not greater than its default "To".
 */
class EntityFieldTypeRangeInt extends EntityField
{
    protected function validateDefaultValue(OrmEntity $entity): void
    {
        parent::validateDefaultValue($entity);

        if (!$entity->isNew() && !$entity->isAttributeChanged('defaultFrom') && !$entity->isAttributeChanged('defaultTo')) {
            return;
        }

        $from = $entity->get('defaultFrom');
        $to   = $entity->get('defaultTo');

        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('defaultFromIsGreaterThanDefaultTo', 'exceptions', 'EntityField'), $from, $to));
        }
    }
}

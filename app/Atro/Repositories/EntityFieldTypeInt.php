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
 * The min of a number field is not greater than its max, the default value is within them.
 */
class EntityFieldTypeInt extends EntityField
{
    protected function validateDefaultValue(OrmEntity $entity): void
    {
        parent::validateDefaultValue($entity);

        if (!$entity->isNew() && !$entity->isAttributeChanged('default') && !$entity->isAttributeChanged('min') && !$entity->isAttributeChanged('max')) {
            return;
        }

        $min     = $entity->get('min');
        $max     = $entity->get('max');
        $default = $entity->get('default');

        if ($min !== null && $max !== null && $min > $max) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('minIsGreaterThanMax', 'exceptions', 'EntityField'), $min, $max));
        }

        if ($default === null) {
            return;
        }

        if ($min !== null && $default < $min) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('defaultValueIsLessThanMin', 'exceptions', 'EntityField'), $default, $min));
        }

        if ($max !== null && $default > $max) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('defaultValueIsGreaterThanMax', 'exceptions', 'EntityField'), $default, $max));
        }
    }
}

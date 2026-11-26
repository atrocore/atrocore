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
 * The default value of a date field is a date.
 */
class EntityFieldTypeDate extends EntityField
{
    protected const DEFAULT_VALUE_FORMAT = 'Y-m-d';

    protected function validateDefaultValue(OrmEntity $entity): void
    {
        parent::validateDefaultValue($entity);

        $default = $entity->get('default');
        if ($default === null || $default === '' || (!$entity->isNew() && !$entity->isAttributeChanged('default'))) {
            return;
        }

        $date = \DateTime::createFromFormat('!' . static::DEFAULT_VALUE_FORMAT, (string)$default);
        if ($date === false || $date->format(static::DEFAULT_VALUE_FORMAT) !== (string)$default) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('defaultValueIsNotDate', 'exceptions', 'EntityField'), $default));
        }
    }
}

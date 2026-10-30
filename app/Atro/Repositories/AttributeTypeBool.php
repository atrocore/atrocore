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

use Atro\Core\Templates\Repositories\Subtype;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

/**
 * A boolean attribute allows the empty value unless it is not null: the values become "false" once it is not null.
 */
class AttributeTypeBool extends Subtype
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() || $entity->isAttributeChanged('allowNullForBool')) {
            $entity->set('notNull', empty($entity->get('allowNullForBool')));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (!$entity->isNew() && $entity->isAttributeChanged('notNull') && $entity->get('notNull')) {
            $table = Util::toUnderScore(lcfirst($entity->get('entityId')) . '_attribute_value');
            $this->getDbal()->createQueryBuilder()
                ->update($table)
                ->set('bool_value', ':false')
                ->where('attribute_id = :attributeId')
                ->andWhere("bool_value is NULL")
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('attributeId', $entity->get('supertypeId'))
                ->executeStatement();
        }
    }
}

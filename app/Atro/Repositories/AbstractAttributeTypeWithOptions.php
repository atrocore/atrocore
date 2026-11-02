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
use Atro\Core\Templates\Repositories\Subtype;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

/**
 * An attribute with options: an option that is used by a value cannot be deleted, the translations of the options
 * are stored as the translations of the attribute.
 */
abstract class AbstractAttributeTypeWithOptions extends Subtype
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('options')) {
            $this->validateDeletedOptions($entity);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->has('translatedOptions')) {
            $this->getEntityManager()->getRepository('Translation')
                ->setTranslationOptions($entity->get('entityId'), $entity->get('systemName'), (array)$entity->get('translatedOptions'));
        }
    }

    protected function validateDeletedOptions(Entity $entity): void
    {
        $deletedOptions = array_values(array_diff((array)($entity->getFetched('options') ?? []), $entity->get('options') ?? []));
        if (empty($deletedOptions)) {
            return;
        }

        $query = $this
            ->getDbal()
            ->createQueryBuilder()
            ->select('id')
            ->from(Util::toUnderScore($entity->get('entityId')) . '_attribute_value')
            ->where('attribute_id=:attributeId')
            ->setParameter('attributeId', $entity->get('supertypeId'));

        $conditions = [$query->expr()->in('varchar_value', ':options')];
        $query->setParameter('options', $deletedOptions, Mapper::getParameterType($deletedOptions));

        foreach ($deletedOptions as $i => $option) {
            $conditions[] = $query->expr()->like('json_value', ":option$i");
            $query->setParameter("option$i", "%\"$option\"%");
        }

        $query->andWhere($query->expr()->or(...$conditions));

        if (!empty($query->fetchOne())) {
            throw new BadRequest($this->getLanguage()->translate('listOptionCannotBeDeleted', 'exceptions'));
        }
    }
}

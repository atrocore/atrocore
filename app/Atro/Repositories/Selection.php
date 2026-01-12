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
use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;


class Selection extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && empty($entity->get('name'))) {

            $user = $this->getEntityManager()->getUser();

            if (!empty($user)) {

                $locale = $user?->getLocale();
                $date = (new \DateTime());

                if (!empty($locale) && !empty($this->getConfig()->get('locale'))) {
                    $locale = $this->getEntityManager()->getEntity('Locale', $locale);
                }

                if (!empty($locale) && !empty($locale->get('timeZone'))) {
                    $date->setTimezone(new \DateTimeZone($locale->get('timeZone')));
                }

                $entity->set('name', $date->format('Y-m-d H:i') . ' by ' . $user->get('name'));
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            if ($entity->get('type') === 'single' && count($this->getEntities($entity->id)) > 1) {
                throw new BadRequest($this->getLanguage()->translate('cannotSetToSingle', 'messages', 'Selection'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    public function getEntities(string $selectionId): array
    {
        $result = $this->getConnection()->createQueryBuilder()
            ->from('selection_record', 'sr')
            ->select('distinct sr.entity_type')
            ->join('sr', 'selection', 's', 'sr.selection_id = s.id')
            ->where('s.id = :selectionId and sr.deleted = :false')
            ->setParameter('selectionId', $selectionId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_column($result, 'entity_type');
    }
}

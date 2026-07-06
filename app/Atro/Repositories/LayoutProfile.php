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

class LayoutProfile extends Base
{
    public function getUserLayoutPreferences(
        string $entityName,
        string $viewType,
        ?string $relatedEntity,
        ?string $relatedLink,
        string $userId
    ): array {
        $conn = $this->getDbal();

        $relatedEntityCondition = empty($relatedEntity) ? "related_entity is null" : "related_entity=:relatedEntity";
        $relatedLinkCondition   = empty($relatedLink)   ? "related_link is null"   : "related_link=:relatedLink";

        $selectedProfileId = $conn->createQueryBuilder()
            ->select('layout_profile_id')
            ->from('user_entity_layout')
            ->where("user_id=:userId and entity=:entity and view_type=:viewType and $relatedEntityCondition and $relatedLinkCondition and deleted=:false")
            ->setParameters([
                'entity'        => $entityName,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity,
                'relatedLink'   => $relatedLink,
                'userId'        => $userId,
            ])
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        $storedProfiles = $conn->createQueryBuilder()
            ->select('lp.id', 'lp.name')
            ->from('layout', 'l')
            ->innerJoin('l', 'layout_profile', 'lp', 'l.layout_profile_id=lp.id')
            ->where("l.entity=:entity and l.view_type=:viewType and $relatedEntityCondition and $relatedLinkCondition and l.deleted=:false and lp.deleted=:false")
            ->setParameters([
                'entity'        => $entityName,
                'viewType'      => $viewType,
                'relatedEntity' => $relatedEntity,
                'relatedLink'   => $relatedLink,
            ])
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $selectedProfileId = $selectedProfileId ?: null;
        if (!empty($selectedProfileId) && !in_array($selectedProfileId, array_column($storedProfiles, 'id'))) {
            $selectedProfileId = null;
        }

        return [
            'storedProfiles'    => $storedProfiles,
            'selectedProfileId' => $selectedProfileId,
        ];
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = array())
    {
        if (empty($options['cascadeChange']) && $entity->isAttributeChanged('isDefault') && $entity->get('isDefault') === false) {
            $profile = $this
                ->select(['id'])
                ->where(['isDefault' => true, 'id!=' => $entity->get('id')])
                ->findOne();

            if (empty($profile)) {
                throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'LayoutProfile'));
            }
        }

        if ($entity->isAttributeChanged('isDefault') && $entity->get('isDefault') === true) {
            foreach ($this->where(['isDefault' => true, 'id!=' => $entity->get('id')])->find() as $profile) {
                $profile->set('isDefault', false);
                $this->getEntityManager()->saveEntity($profile, ['cascadeChange' => true]);
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('isDefault'))) {
            throw new BadRequest($this->getInjection('language')->translate('defaultIsRequired', 'exceptions', 'LayoutProfile'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ((empty($options['cascadeChange']) && $entity->isAttributeChanged('isDefault') && $entity->get('isDefault') === true) ||
            $entity->isAttributeChanged('hideShowFullList') || $entity->isAttributeChanged('navigation')) {
            $this->getInjection('dataManager')->clearCache(true);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
    }
}

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
use Atro\Core\Utils\Metadata;
use Espo\ORM\Entity;

class LayoutProfile extends Base
{
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

    public function prepareLayoutProfileData(\stdClass $preferenceData): void
    {
        $layoutProfile = $this->getEntityManager()->getUser()->get('layoutProfile') ?? $this->where(['isDefault' => true])->findOne();
        if (empty($layoutProfile)) {
            return;
        }

        $navigation = [];
        if (!empty($layoutProfile->get('navigation'))) {
            $navigation = $layoutProfile->get('navigation');
        }

        $preparedNavigation = [];
        if (!empty($navigation)) {
            /** @var Metadata $metadata */
            $metadata = $this->getInjection('container')->get('metadata');

            foreach ($navigation as $item) {
                if (is_string($item)) {
                    if ($metadata->get("scopes.$item.tab")) {
                        $preparedNavigation[] = $item;
                    }
                } else {
                    if (!empty($item->items)) {
                        $newSubItems = [];
                        foreach ($item->items as $subItem) {
                            if ($metadata->get("scopes.$subItem.tab")) {
                                $newSubItems[] = $subItem;
                            }
                        }
                        if (!empty($newSubItems)) {
                            $item->items = $newSubItems;
                            $preparedNavigation[] = $item;
                        }
                    }
                }
            }
        }

        $preferenceData->lpNavigation = $preparedNavigation;
        $preferenceData->hideShowFullList = $layoutProfile->get('hideShowFullList') ?? false;
        $preferenceData->layoutProfileId = $layoutProfile->get('id');

        if (empty($preferenceData->dashboardLayout)) {
            $preferenceData->dashboardLayout = $layoutProfile->get('dashboardLayout');
            $preferenceData->dashletsOptions = $layoutProfile->get('dashletsOptions');
        }

        if (empty($preferenceData->favoritesList)) {
            $preferenceData->favoritesList = array_values(array_filter($layoutProfile->get('favoritesList') ?? [], fn($item) => !!$metadata->get("scopes.$item.tab")));
        }
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
            $this->getInjection('dataManager')->clearCache();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
        $this->addDependency('container');
    }
}

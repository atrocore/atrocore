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

namespace Atro\Services;

use Espo\ORM\Entity;

class UserProfile extends User
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($entity->get('localeId'))) {
            $locale = $this->getEntityManager()->getRepository('Locale')->get($entity->get('localeId'));
            if (!empty($locale)) {
                $entity->set('localeName', $locale->get('name'));
            }
        }

        if (!empty($entity->get('styleId'))) {
            $style = $this->getEntityManager()->getRepository('Style')->get($entity->get('styleId'));
            if (!empty($style)) {
                $entity->set('styleName', $style->get('name'));
            }
        }

        $this->prepareLayoutProfileData($entity);
    }

    public function prepareLayoutProfileData(Entity $entity): void
    {
        $layoutProfile = $this->getUser()->get('layoutProfile');
        if (empty($layoutProfile)) {
            $layoutProfile = $this->getEntityManager()->getRepository('LayoutProfile')
                ->where(['isDefault' => true])
                ->findOne();
        }

        if (empty($layoutProfile)) {
            return;
        }

        $navigation = [];
        if (!empty($layoutProfile->get('navigation'))) {
            $navigation = $layoutProfile->get('navigation');
        }

        $preparedNavigation = [];
        if (!empty($navigation)) {
            foreach ($navigation as $item) {
                if (is_string($item)) {
                    if ($this->getMetadata()->get("scopes.$item.tab")) {
                        $preparedNavigation[] = $item;
                    }
                } else {
                    if (!empty($item->items)) {
                        $newSubItems = [];
                        foreach ($item->items as $subItem) {
                            if ($this->getMetadata()->get("scopes.$subItem.tab")) {
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

        $entity->set('lpNavigation', $preparedNavigation);
        $entity->set('hideShowFullList', $layoutProfile->get('hideShowFullList') ?? false);
        $entity->set('layoutProfileId', $layoutProfile->get('id'));

        if (empty($entity->get('dashboardLayout'))) {
            $entity->set('dashboardLayout', $layoutProfile->get('dashboardLayout'));
            $entity->set('dashletsOptions', $layoutProfile->get('dashletsOptions'));
        }

        if (empty($entity->get('favoritesList'))) {
            $entity->set('favoritesList', array_values(array_filter($layoutProfile->get('favoritesList') ?? [],
                fn($item) => !!$this->getMetadata()->get("scopes.$item.tab"))));
        }
    }
}

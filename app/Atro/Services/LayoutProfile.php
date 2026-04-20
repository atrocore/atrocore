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

namespace Atro\Services;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\Error;
use Atro\Core\LayoutManager;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

class LayoutProfile extends Base
{

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if(empty($navigation = $entity->get('navigation'))) {
            return;
        }

        $preparedNavigation = [];

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

        $entity->set('navigation', $preparedNavigation);
    }

    public function updateLayout(
        string $layoutProfileId,
        string $entityName,
        string $viewType,
        string $relatedEntity,
        string $relatedLink,
        array $layout
    ): array {
        $lm = $this->getLayoutManager();
        $lm->checkLayoutProfile($layoutProfileId);

        if ($lm->save($entityName, $viewType, $relatedEntity, $relatedLink, $layoutProfileId, $layout) === false) {
            throw new Error('Error while saving layout.');
        }

        $this->getDataManager()->clearCache(true);

        return $lm->get($entityName, $viewType, $relatedEntity, $relatedLink, $layoutProfileId);
    }

    protected function getLayoutManager(): LayoutManager
    {
        return $this->getInjection('layoutManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('layoutManager');
        $this->addDependency('dataManager');
    }

    protected function duplicateLayouts(IEntity $entity, IEntity $duplicatingEntity)
    {
        $layoutRepo = $this->getEntityManager()->getRepository('Layout');
        foreach ($duplicatingEntity->get('layouts') as $layout) {
            $record = $this->getEntityManager()->getEntity('Layout');
            $record->set('entity', $layout->get('entity'));
            $record->set('viewType', $layout->get('viewType'));
            $record->set('layoutProfileId', $entity->get('id'));
            try {
                $this->getEntityManager()->saveEntity($record);
                $layoutRepo->saveContent($record, $layout->getData(false));
            } catch (\Throwable $e) {
                $GLOBALS['log']->error("Duplicating layout failed: {$e->getMessage()}");
            }
        }
    }
}

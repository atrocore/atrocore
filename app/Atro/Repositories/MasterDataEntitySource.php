<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class MasterDataEntitySource extends Base
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew() || $entity->isAttributeChanged('sourceEntity')) {
            $this->getDataManager()->rebuild();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getDataManager()->rebuild();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }
}

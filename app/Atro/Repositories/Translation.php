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
use Espo\Core\DataManager;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Translation extends ReferenceData
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('module') === 'custom' && !$entity->isNew() && !$entity->get('isCustomized')) {
            $entity->set('isCustomized', true);
        }

        parent::beforeSave($entity, $options);
    }
//
//    /**
//     * @inheritDoc
//     */
//    protected function afterSave(Entity $entity, array $options = [])
//    {
//        parent::afterSave($entity, $options);
//
//        $this->refreshTimestamp($options);
//    }
//
//    /**
//     * @inheritDoc
//     */
//    protected function afterRemove(Entity $entity, array $options = [])
//    {
//        parent::afterRemove($entity, $options);
//
//        $this->refreshTimestamp($options);
//    }
//
//    protected function refreshTimestamp(array $options): void
//    {
//        if (!empty($options['keepCache'])) {
//            return;
//        }
//
//        $this->getInjection('language')->clearCache();
//
//        $this->getConfig()->set('cacheTimestamp', time());
//        $this->getConfig()->save();
//        DataManager::pushPublicData('dataTimestamp', time());
//    }
//
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}

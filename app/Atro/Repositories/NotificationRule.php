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
use Espo\Core\DataManager;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class NotificationRule extends Base
{
    const CACHE_NAME = 'notification_rule';

    public function deleteCacheFile(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $file = DataManager::CACHE_DIR_PATH . '/' . self::CACHE_NAME . '.json';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            return;
        }

        if ($entity->isAttributeChanged('occurrence') || $entity->isAttributeChanged('entity')) {
            throw new BadRequest('You cannot update the attribute occcurrence or entity');
        }
        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if (($entity->isNew() && $entity->get('isActive')) || $entity->isAttributeChanged('isActive')) {
            $this->deleteCacheFile();
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('isActive')) {
            $this->deleteCacheFile();
        }

        parent::afterRemove($entity, $options);
    }

    protected function afterRestore($entity)
    {
        if ($entity->get('isActive')) {
            $this->deleteCacheFile();
        }

        parent::afterRestore($entity);
    }


    public function findFromCache(string $id): ?\Atro\Entities\NotificationRule
    {
        if (!$this->getDataManager()->isUseCache(self::CACHE_NAME)) {
            return $this->get($id);
        }

        $notificationRules = $this->getDataManager()->getCacheData(self::CACHE_NAME);

        $result = array_filter($notificationRules, function ($rule) use ($id) {
            return $rule['id'] === $id;
        });

        if (!empty($result)) {
            $result = array_values($result);
            $entity = $this->get();
            $entity->set(Util::arrayKeysToCamelCase($result[0]));
            return $entity;
        }

        return null;
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }
}

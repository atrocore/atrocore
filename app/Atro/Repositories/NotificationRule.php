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

        if (($entity->isNew() || !$entity->isAttributeChanged('isActive')) && !$entity->get('isActive')) {
            return;
        }

        $this->deleteCacheFile();
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


    public function findOneFromCache(string $notificationProfileId, string $occurrence, string $entity): ?\Atro\Entities\NotificationRule
    {
        if (!$this->getDataManager()->isUseCache(self::CACHE_NAME)) {
            return $this->where([
                "notificationProfileId" => $notificationProfileId,
                "occurrence" => $occurrence,
                "entity=" => $entity
            ])->findOne();
        }

        $notificationRules = $this->getDataManager()->getCacheData(self::CACHE_NAME);

        if(empty($notificationRules)){
            return null;
        }

        $result = array_filter($notificationRules, function ($rule) use ($notificationProfileId, $entity, $occurrence) {
            return $rule['notification_profile_id'] === $notificationProfileId
                && $rule['occurrence'] === $occurrence
                && $rule['entity'] === $entity;
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

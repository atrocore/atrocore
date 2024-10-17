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

use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Language;
use Espo\ORM\Entity;

class UiHandler extends ReferenceData
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew()) {
            foreach (['type', 'isActive', 'entityType', 'fields', 'conditionsType', 'conditions'] as $field) {
                if ($entity->isAttributeChanged($field)) {
                    $this->validateSystemHandler($entity);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->refreshCache();

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateSystemHandler($entity);

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->refreshCache();

        parent::afterRemove($entity, $options);
    }

    public function validateSystemHandler(Entity $entity): void
    {
        if (!empty($entity->get('system'))) {
            throw new BadRequest(sprintf($this->getLanguage()->translate('systemHandler', 'exceptions', 'UiHandler'), $entity->get('name')));
        }
    }

    public function refreshCache(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('memoryStorage');
        $this->addDependency('language');
    }
}

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\Core\DataManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class UiHandler extends Base
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
        $this->deleteCacheFile();

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $this->validateSystemHandler($entity);

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterRemove($entity, $options);
    }

    public function validateSystemHandler(Entity $entity): void
    {
        if (mb_substr($entity->get('id'), 0, 3) === 'ui_') {
            throw new BadRequest(
                sprintf($this->getLanguage()->translate('systemHandler', 'exceptions', 'UiHandler'), $entity->get('name'))
            );
        }
    }

    public function deleteCacheFile(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $file = DataManager::CACHE_DIR_PATH . '/ui_handler.json';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }
}

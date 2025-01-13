<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Atro\Core\DataManager;
use Espo\ORM\Entity;

class PreviewTemplate extends Base
{
    public const CACHE_NAME = 'preview_template';

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterRemove($entity, $options);
    }

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
}
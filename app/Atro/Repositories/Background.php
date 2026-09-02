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

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\DataManager;
use Espo\ORM\Entity;

class Background extends ReferenceData
{
    /**
     * The fields of this entity that the config exposes.
     */
    public static function getConfigData(): array
    {
        $path = self::DIR_PATH . '/Background.json';

        if (!file_exists($path)) {
            return [];
        }

        $items = @json_decode(file_get_contents($path), true);

        if (!is_array($items)) {
            return [];
        }

        $res = [];
        foreach ($items as $key => $row) {
            $res[$key] = [
                'id' => $row['id'] ?? null,
                'code' => $row['code'] ?? null,
                'imageId' => $row['imageId'] ?? null,
            ];
        }

        return $res;
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->getDataManager()->clearCache(true);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->getDataManager()->clearCache();
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

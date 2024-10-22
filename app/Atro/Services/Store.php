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

use Atro\Core\ModuleManager\Manager as ModuleManager;
use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($entity->get('id') === 'Atro') {
            $entity->set('currentVersion', Composer::getCoreVersion());
            $entity->set('settingVersion', self::getSettingVersion(Composer::getComposerJson(), 'atrocore/core'));
            $entity->set('isSystem', true);
            $entity->set('isComposer', true);
        } else {
            $module = $this->getModuleManager()->getModule($entity->get('id'));
            if (!empty($module)) {
                $entity->set('currentVersion', $module->getVersion());
                $entity->set('settingVersion',
                    self::getSettingVersion(Composer::getComposerJson(), $module->getComposerName()));
                $entity->set('isSystem', $module->isSystem());
                $entity->set('isComposer', !empty($module->getVersion()));
            }
        }

        $versions = $entity->get('versions') ?? [];
        $lastVersion = array_shift($versions);
        $entity->set('latestVersion', $lastVersion['version'] ?? null);
    }

    public static function getSettingVersion(array $composerData, string $name): string
    {
        if (isset($composerData['require'][$name])) {
            return ModuleManager::prepareVersion($composerData['require'][$name]);
        }

        return '';
    }

    protected function getModuleManager(): ModuleManager
    {
        return $this->getInjection('moduleManager');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('moduleManager');
    }
}

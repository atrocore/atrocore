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
use Atro\Services\Composer;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class Store extends ReferenceData
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

//        [
//            'id'             => 'TreoCore',
//            'name'           => $this->translate('Core'),
//            'description'    => $this->translate('Core', 'descriptions'),
//            'currentVersion' => self::getCoreVersion(),
//            'latestVersion'  => $this->getLatestVersion('Atro'),
//            'isSystem'       => true,
//            'isComposer'     => true,
//            'status'         => $this->getModuleStatus($composerDiff, 'Atro'),
//            'settingVersion' => self::getSettingVersion($composerData, 'atrocore/core')
//        ]
//
//        if ($entity->get('code') === 'atrocore/core') {
//            $entity->set('status', 'installed');
//            $entity->set('currentVersion', Composer::getCoreVersion());
//            $entity->set('latestVersion', '1.11.18');
//            $entity->set('settingVersion', '^1.11.21');
//            $entity->set('isSystem', true);
//            $entity->set('isComposer', true);
//        }

        $module = $this->getModuleManager()->getModule($entity->get('id'));
        if (!empty($module)) {
            $versions = $entity->get('versions') ?? [];
            $lastVersion = array_shift($versions);

            $entity->set('currentVersion', $module->getVersion());
            $entity->set('latestVersion', $lastVersion['version'] ?? null);
            $entity->set('settingVersion', self::getSettingVersion(Composer::getComposerJson(), $module->getComposerName()));
            $entity->set('isSystem', $module->isSystem());
            $entity->set('isComposer', !empty($module->getVersion()));
        }
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

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
use Atro\Core\Templates\Repositories\ReferenceData;
use Espo\ORM\Entity;

class Language extends ReferenceData
{
    /**
     * The fields of this entity that the config exposes.
     */
    public static function getConfigData(): array
    {
        $path = self::DIR_PATH . '/Language.json';

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
                'name' => $row['name'] ?? null,
                'role' => $row['role'] ?? null,
            ];
        }

        return $res;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $items = $this->getAllItems();

        if ($entity->isNew()) {
            if ($entity->get('role') === 'main') {
                foreach ($items as $item) {
                    if ($item['role'] === 'main') {
                        throw new BadRequest($this->getLanguage()->translate('mainLanguageIsAlreadyExists', 'exceptions', 'Language'));
                    }
                }
            }
        } else {
            if ($entity->isAttributeChanged('role')) {
                throw new BadRequest($this->getLanguage()->translate('roleCannotBeChanged', 'exceptions', 'Language'));
            }
            if ($entity->get('role') === 'additional' && $entity->isAttributeChanged('code')) {
                throw new BadRequest($this->getLanguage()->translate('codeForAdditionalCannotBeChanged', 'exceptions', 'Language'));
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isNew() && $entity->get('role') === 'additional') {
            $this->rebuild();
        } else {
            $this->clearCache();
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if ($entity->get('role') === 'main') {
            throw new BadRequest($this->getLanguage()->translate('mainLanguageCannotBeRemoved', 'exceptions', 'Language'));
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        if ($entity->get('role') === 'additional') {
            $this->rebuild();
        } else {
            $this->clearCache();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }

    protected function clearCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
    }

    protected function rebuild(): void
    {
        $this->getInjection('dataManager')->rebuild();
    }
}

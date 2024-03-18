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

use Atro\Core\Templates\Repositories\Base;
use  Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Class Library
 */
class Library extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($entity->get('id') === '1') {
            throw new BadRequest($this->translate("defaultLibraryCantBeDeleted", 'exceptions', 'Library'));
        }

        parent::beforeRemove($entity, $options);
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == "assetCategories" && !$this->isValidCategory($foreign)) {
            throw new BadRequest($this->translate('libraryCanBeLinkedWithRootCategoryOnly', 'exceptions', 'Library'));
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isValidCategory(Entity $entity): bool
    {
        if (is_string($entity)) {
            $entity = $this->getEntityManager()->getEntity("AssetCategory", $entity);
        }

        return !$entity->get("categoryParentId");
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function translate(string $key, string $category, string $scope): string
    {
        return $this->getInjection('language')->translate($key, $category, $scope);
    }
}

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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class AssetType extends Base
{
    public function deleteValidationRules(Entity $entity): void
    {
        $this
            ->getEntityManager()
            ->getRepository('ValidationRule')
            ->where(['assetTypeId' => $entity->get('id')])
            ->removeCollection();
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->clearCache();
    }

    public function isInUse(Entity $entity): bool
    {
        $asset = $this
            ->getEntityManager()
            ->getRepository('Asset')
            ->select(['id'])
            ->where(['type*' => '%"' . ($entity->isNew() ? $entity->get('name') : $entity->getFetched('name')) . '"%'])
            ->findOne();

        return !empty($asset);
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('name') && $this->isInUse($entity)) {
            throw new BadRequest($this->getInjection('container')->get('language')->translate('assetTypeInUseRename', 'exceptions', 'AssetType'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->clearCache();

        parent::afterSave($entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if ($this->isInUse($entity)) {
            throw new BadRequest($this->getInjection('container')->get('language')->translate('assetTypeInUseDelete', 'exceptions', 'AssetType'));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteValidationRules($entity);

        $this->clearCache();

        parent::afterRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('dataManager');
    }
}

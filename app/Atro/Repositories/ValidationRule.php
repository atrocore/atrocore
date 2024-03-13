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

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ValidationRule extends Base
{
    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = array())
    {
        // set name
        $entity->set('name', $entity->get('type'));

        parent::beforeSave($entity, $options);
    }

    public function save(Entity $entity, array $options = [])
    {
        $inTransaction = false;
        if (!$this->getEntityManager()->getPDO()->inTransaction()) {
            $this->getEntityManager()->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            $result = parent::save($entity, $options);
            $this->recheckAllAssets($entity);
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if ($inTransaction) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    public function recheckAllAssets(Entity $entity): void
    {
        $assetType = $entity->get('assetType');
        if (empty($assetType)) {
            return;
        }

        $assets = $this
            ->getEntityManager()
            ->getRepository('Asset')
            ->select(['id'])
            ->where(['type*' => '%"' . $assetType->get('name') . '"%'])
            ->find();

        if (count($assets) === 0) {
            return;
        }

        foreach ($assets as $asset) {
            $this->getInjection('pseudoTransactionManager')->pushCustomJob('Asset', 'recheckAssetTypes', ['assetId' => $asset->get('id')]);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('pseudoTransactionManager');
    }
}

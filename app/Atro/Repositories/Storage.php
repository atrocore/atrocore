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
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class Storage extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (!$entity->isNew()) {
            if ($entity->isAttributeChanged('type')) {
                throw new BadRequest($this->translate('storageTypeCannotBeChanged', 'exceptions', 'Storage'));
            }
            if ($entity->get('type') === 'local' && $entity->isAttributeChanged('path')) {
                throw new BadRequest($this->translate('storagePathCannotBeChanged', 'exceptions', 'Storage'));
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $e = $this->getEntityManager()->getRepository('File')
            ->select(['id'])
            ->where([
                'storageId' => $entity->get('id')
            ])
            ->findOne();

        if (!empty($e)) {
            throw new BadRequest($this->translate('storageWithFilesCannotBeRemoved', 'exceptions', 'Storage'));
        }

        parent::beforeRemove($entity, $options);
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

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

namespace Atro\Core\Services\Traits;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Espo\ORM\Entity;

/**
 * Deleting the records.
 */
trait DeletableTrait
{
    public function deleteEntity(string $id): bool
    {
        // the records of such an entity cannot be deleted at all
        if (!empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'deleteDisabled']))) {
            throw new Forbidden($this->getInjection('language')->translate('recordCannotBeDeleted', 'exceptions'));
        }

        $id = $this
            ->dispatchEvent('beforeDeleteEntity', new Event(['id' => $id, 'service' => $this]))
            ->getArgument('id');

        if (empty($id)) {
            throw new BadRequest();
        }

        if (!empty($this->getMetadata()->get(['scopes', $this->getEntityType(), 'disabled']))) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get($id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'delete')) {
            throw new Forbidden();
        }

        $this->beforeDeleteEntity($entity);

        $result = $this->getRepository()->remove($entity, $this->getDefaultRepositoryOptions());
        if ($result) {
            $this->afterDeleteEntity($entity);
            $result = $this->dispatchEvent('afterDeleteEntity', new Event(['id' => $id, 'result' => $result, 'service' => $this]))->getArgument('result');
        }

        return $result;
    }

    protected function beforeDeleteEntity(Entity $entity)
    {
    }

    protected function afterDeleteEntity(Entity $entity)
    {
    }
}

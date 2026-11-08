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
use Atro\Core\Exceptions\NotFound;

/**
 * Restoring the deleted records and deleting them permanently.
 */
trait SoftDeletableTrait
{
    public function restoreEntity($id)
    {
        $id = $this
            ->dispatchEvent('beforeRestoreEntity', new Event(['id' => $id, 'service' => $this]))
            ->getArgument('id');

        if (empty($id)) {
            throw new BadRequest();
        }

        $this->beforeRestoreEntity($id);
        $entity = $this->getRepository()->restore($id);
        if ($entity) {
            $this->afterRestoreEntity($entity);
            return $this->dispatchEvent('afterRestoreEntity', new Event(['id' => $id, 'result' => $entity, 'service' => $this]))->getArgument('result');
        }

        return false;
    }

    protected function beforeRestoreEntity($entity)
    {
    }

    protected function afterRestoreEntity($entity)
    {
    }

    public function deleteEntityPermanently(string $id): bool
    {
        try {
            $deleted = $this->deleteEntity($id);
        } catch (NotFound $e) {
            if (empty($this->getRepository()->markedAsDeleted($id))) {
                throw new NotFound();
            }
            $deleted = true;
        }

        $res = false;

        if ($deleted) {
            $id = $this
                ->dispatchEvent('beforeDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this]))
                ->getArgument('id');
            if (!empty($id)) {
                $this->getRepository()->deleteFromDb($id);
            }

            $res = true;
        }

        return $this
            ->dispatchEvent('afterDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this, 'res' => $res]))
            ->getArgument('res');
    }
}

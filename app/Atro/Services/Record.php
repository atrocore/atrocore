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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\EventManager\Event;

class Record extends \Espo\Services\RecordService
{
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

        if ($deleted) {
            $id = $this
                ->dispatchEvent('beforeDeleteEntityPermanently', new Event(['id' => $id, 'service' => $this]))
                ->getArgument('id');
            if (!empty($id)) {
                $this->getRepository()->deleteFromDb($id);
            }

            return true;
        }

        return false;
    }
}

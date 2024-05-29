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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Hierarchy;

class Folder extends Hierarchy
{
    protected $entityType = "Folder";

    protected ?Storage $storage = null;

    public function getStorage(): Storage
    {
        if (!$this->storage === null) {
            $this->storage = $this->getEntityManager()->getRepository('Storage')->get($this->get('storageId'));
        }

        return $this->storage;
    }
}

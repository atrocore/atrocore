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

namespace Atro\ActionTypes;

use Atro\Core\Exceptions\NotModified;
use Espo\ORM\Entity;

class CreateOrUpdate extends Create
{
    protected function updateTargetEntity(string $id, \stdClass $input, Entity $action): void
    {
        try {
            $this->getService($action->get('targetEntity'))->updateEntity($id, $input);
        } catch (NotModified $e) {
        }
    }
}

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ActionTypes;

use Atro\Core\EventManager\Event;
use Espo\ORM\Entity;

interface TypeInterface
{
    public function executeViaWorkflow(array $workflowData, Event $event): bool;

    public function executeNow(Entity $action, \stdClass $input): bool;
}

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

namespace Atro\ActionTypes;

use Espo\ORM\Entity;

class Webhook extends AbstractAction
{
    public function executeNow(Entity $action, \stdClass $input): bool
    {
        if (!empty($action->get('webhookUrl'))){
            $res = @file_get_contents($action->get('webhookUrl'));
        }

        return true;
    }
}

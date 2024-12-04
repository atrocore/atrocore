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

use Atro\Core\Templates\Services\Base;
use Atro\Core\EventManager\Event;
use Espo\ORM\Entity;

class Bookmark extends Base
{
    public function findEntities($params)
    {
        $params['where'][] = [
            "attribute" => "ownerUserId",
            "type" => "equals",
            "value" => $this->getUser()->id
        ];
        
        return parent::findEntities($params);
    }
}

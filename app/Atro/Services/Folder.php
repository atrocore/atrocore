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

use Atro\Core\Templates\Services\Hierarchy;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'parents' || $link === 'children' || $link === 'files') {
            $params['where'][] = [
                'type'  => 'bool',
                'value' => ['hiddenAndUnHidden']
            ];
        }

        return parent::findLinkedEntities($id, $link, $params);
    }
}

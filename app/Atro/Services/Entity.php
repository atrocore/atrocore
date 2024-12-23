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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\ReferenceData;

class Entity extends ReferenceData
{
    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'fields') {
            return $this->getEntityFields($id);
        }

        throw new BadRequest();
    }

    public function getEntityFields(string $code): array
    {
        return ['list' => []];
    }
}

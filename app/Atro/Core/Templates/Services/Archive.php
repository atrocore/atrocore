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

namespace Atro\Core\Templates\Services;

use Atro\Core\Exceptions\Forbidden;
use Atro\Services\Record;

class Archive extends Record
{
    public function createEntity($attachment)
    {
        throw new Forbidden();
    }

    public function updateEntity($id, $data)
    {
        throw new Forbidden();
    }

    public function deleteEntity($id)
    {
        throw new Forbidden();
    }

    public function restoreEntity($id)
    {
        throw new Forbidden();
    }

    public function follow($id, $userId = null)
    {
        throw new Forbidden();
    }

    public function unfollow($id, $userId = null)
    {
        throw new Forbidden();
    }
}

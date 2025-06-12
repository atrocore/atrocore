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

namespace Atro\Controllers;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class RoleScopeField extends Base
{
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }
}

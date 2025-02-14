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

namespace Atro\Controllers;

use Atro\Core\Exceptions\BadRequest;

class App extends \Espo\Controllers\App
{
    public function postActionStartEntityListening($params, $data, $request)
    {
        if (empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        return $this->getService('App')->startEntityListening($data->entityName, $data->entityId);
    }
}
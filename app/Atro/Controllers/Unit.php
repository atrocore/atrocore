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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Controllers\Base;

class Unit extends Base
{
    public function actionSetDefault($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $unit = $this->getEntityManager()->getEntity('Unit', $data->id);
        if (!$unit) {
            throw new NotFound();
        }

        $this->getRecordService()->setUnitAsDefault($unit);

        return true;
    }
}

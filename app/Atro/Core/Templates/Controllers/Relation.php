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

namespace Atro\Core\Templates\Controllers;

use Espo\Core\Controllers\Record;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class Relation extends Record
{
    public function actionInheritRelation($params, $data, $request)
    {
        if (!$request->isPut()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        $entity = $this->getRecordService()->inheritRelation($data);

        return $entity->toArray();
    }
}

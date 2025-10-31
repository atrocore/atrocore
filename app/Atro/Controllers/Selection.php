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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class Selection extends Base
{
   public function actionCreateSelectionWithRecords($params, $data, $request)
   {
       if (!$request->isPost() || empty($data->scope) || empty($data->entityIds)) {
           throw new BadRequest();
       }

       $selection =  $this->getRecordService()->createSelectionWithRecords($data->scope, $data->entityIds);

       return $selection->getValueMap();
   }

}

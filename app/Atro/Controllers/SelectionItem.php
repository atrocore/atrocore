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
use Atro\Core\Templates\Controllers\Base;

class SelectionItem extends Base
{

    public function actionReplaceItem($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->id) || empty($data->selectedRecords)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->replaceItem($data->id, $data->selectedRecords[0]);
    }

    public function actionCreateOnCurrentSelection($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->entityType) || empty($data->entityId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->createOnCurrentItem($data->entityType, $data->entityId);
    }
}

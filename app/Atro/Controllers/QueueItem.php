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

use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;

class QueueItem extends Base
{
    /**
     * @inheritdoc
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    public function actionMassCancel($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        return $this->getRecordService()->massCancel($data);
    }
}

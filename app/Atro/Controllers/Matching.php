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
use Atro\Core\Templates\Controllers\ReferenceData;

class Matching extends ReferenceData
{
    public function actionMatchedRecords($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get('code')) || empty($request->get('entityName')) || empty($request->get('entityId'))) {
            throw new BadRequest();
        }

        return $this
            ->getRecordService()
            ->getMatchedRecords($request->get('code'), $request->get('entityName'), $request->get('entityId'));
    }
}

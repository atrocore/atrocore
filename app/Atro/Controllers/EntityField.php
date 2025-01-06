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

use Atro\Core\Templates\Controllers\ReferenceData;
use Atro\Core\Exceptions\BadRequest;

class EntityField extends ReferenceData
{
    public function actionRenderScriptPreview($params, $data, $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        return $this->getRecordService()->renderScriptPreview($data);
    }
}

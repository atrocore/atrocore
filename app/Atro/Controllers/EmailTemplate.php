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

class EmailTemplate extends ReferenceData
{
    public function actionPreview($params, $data, $request): array
    {
        if (!$request->isPost() || !property_exists($data, 'id') || !property_exists($data, 'scope') || !property_exists($data, 'entityId')) {
            throw new BadRequest();
        }

        return $this
            ->getRecordService()
            ->getPreview((string)$data->id, (string)$data->scope, (string)$data->entityId);
    }
}

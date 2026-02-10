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

use Atro\Controllers\AbstractRecordController;
use Atro\Core\Exceptions\BadRequest;
use Atro\Services\MasterDataEntity;

class Base extends AbstractRecordController
{
    public function actionUpdateMasterRecord($params, $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        /** @var MasterDataEntity $service */
        $service = $this->getServiceFactory()->create('MasterDataEntity');

        return $service->updateMasterRecordByStagingEntity($this->name, (string)$data->id);
    }
}

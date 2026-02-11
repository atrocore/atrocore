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
use Atro\Core\Exceptions\NotFound;

class Base extends AbstractRecordController
{
    public function actionUpdateMasterRecord($params, $data, $request): bool
    {
        if (empty($this->getMetadata()->get("scopes.{$this->name}.primaryEntityId"))) {
            throw new NotFound();
        }

        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        $staging = $this->getEntityManager()->getRepository($this->name)->get((string)$data->id);
        if (empty($staging)) {
            throw new NotFound();
        }

        $this->getServiceFactory()->create('MasterDataEntity')->updateMasterRecord($staging);

        return true;
    }
}

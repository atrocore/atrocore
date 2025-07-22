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

class Attribute extends Base
{
    public function actionAttributesDefs($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get('entityName')) || empty($request->get('attributesIds'))) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesDefs(
            $request->get('entityName'),
            $request->get('attributesIds')
        );
    }

    public function actionAddAttributeValue($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'ids') && !property_exists($data, 'where')){
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        if (
            $this->getMetadata()->get(['scopes', $data->entityName, 'hasAttribute'])
            && $this->getMetadata()->get(['scopes', $data->entityName, 'disableAttributeLinking'])
        ) {
            throw new BadRequest();
        }

        return $this->getRecordService()->addAttributeValue(
            $data->entityName,
            $data->entityId,
            $data->where ?? null,
            $data->ids ?? null
        );
    }

    public function actionRemoveAttributeValue($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->entityName) || empty($data->attributeId) || empty($data->entityId)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        if (
            $this->getMetadata()->get(['scopes', $data->entityName, 'hasAttribute'])
            && $this->getMetadata()->get(['scopes', $data->entityName, 'disableAttributeLinking'])
        ) {
            throw new BadRequest();
        }

        return $this->getRecordService()->removeAttributeValue($data->entityName, $data->entityId, $data->attributeId);
    }
}

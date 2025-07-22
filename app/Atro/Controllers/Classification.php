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
use Atro\Core\Templates\Controllers\Hierarchy;
use Atro\Services\Record;

class Classification extends Hierarchy
{
    public function actionRelateRecords($params, $data, $request)
    {
        if (
            !$request->isPost()
            || !property_exists($data, 'entityName')
            || !property_exists($data, 'entityId')
            || !property_exists($data, 'classificationsIds')
        ) {
            throw new BadRequest();
        }

        /** @var Record $service */
        $service = $this->getServiceFactory()->create($data->entityName);

        if (empty($data->classificationsIds)) {
            $service->unlinkAll($data->entityId, 'classifications');
        } else {
            $recordClassificationsIds = [];

            $res = $service->findLinkedEntities($data->entityId, 'classifications', []);
            if (!empty($res['collection'][0])) {
                $recordClassificationsIds = array_column($res['collection']->toArray(), 'id');
            }

            foreach ($recordClassificationsIds as $recordClassificationId) {
                if (!in_array($recordClassificationId, $data->classificationsIds)) {
                    $service->unlinkEntity($data->entityId, 'classifications', $recordClassificationId);
                }
            }

            foreach ($data->classificationsIds as $classificationId) {
                if (!in_array($classificationId, $recordClassificationsIds)) {
                    $service->linkEntity($data->entityId, 'classifications', $classificationId);
                }
            }
        }

        return true;
    }
}

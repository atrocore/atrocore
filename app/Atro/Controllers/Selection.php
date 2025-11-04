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

class Selection extends Base
{
    public function actionCreateSelectionWithRecords($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->scope) || empty($data->entityIds)) {
            throw new BadRequest();
        }

        $selection = $this->getRecordService()->createSelectionWithRecords($data->scope, $data->entityIds);

        return $selection->getValueMap();
    }

    public function actionTree($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('link')) || (empty($request->get('selectedScope')) && empty($request->get('scope')))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $params = [
            'where'       => $this->prepareWhereQuery($request->get('where')),
            'asc'         => $request->get('asc', 'true') === 'true',
            'sortBy'      => $request->get('sortBy'),
            'isTreePanel' => !empty($request->get('isTreePanel')),
            'offset'      => (int)$request->get('offset'),
            'maxSize'     => empty($request->get('maxSize')) ? $this->getConfig()->get('recordsPerPageSmall', 20) : (int)$request->get('maxSize')
        ];

        return $this->getRecordService()->getTreeItems((string)$request->get('link'), (string)($request->get('selectedScope') ?? $request->get('scope')), $params);
    }

}

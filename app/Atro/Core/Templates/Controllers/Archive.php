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

use Atro\Controllers\AbstractController;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;

class Archive extends AbstractController
{
    const MAX_SIZE_LIMIT = 200;

    public static $defaultAction = 'list';

    public function actionRead($params, $data, $request)
    {
        $id = $params['id'];
        $entity = $this->getRecordService()->readEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        return $entity->getValueMap();
    }

    public function actionList($params, $data, $request)
    {
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $where = $this->prepareWhereQuery($request->get('where'));
        $offset = $request->get('offset');
        $maxSize = $request->get('maxSize') ?? self::MAX_SIZE_LIMIT;
        $asc = $request->get('asc', 'true') === 'true';
        $sortBy = $request->get('sortBy');
        $q = $request->get('q');
        $textFilter = $request->get('textFilter');
        $collectionOnly = $request->get('collectionOnly') === 'true';
        $totalOnly = $request->get('totalOnly') === 'true';

        $params = [
            'where'          => $where,
            'offset'         => $offset,
            'maxSize'        => $maxSize,
            'asc'            => $asc,
            'sortBy'         => $sortBy,
            'q'              => $q,
            'textFilter'     => $textFilter,
            'totalOnly'      => $totalOnly,
            'collectionOnly' => $collectionOnly,
        ];

        if (!empty($request->get('attributes'))) {
            $params['attributesIds'] = explode(',', $request->get('attributes'));
        }

        if ($request->get('allAttributes') === 'true' || $request->get('allAttributes') === '1') {
            $params['allAttributes'] = true;
        }

        $this->fetchListParamsFromRequest($params, $request, $data);

        $result = $this->getRecordService()->findEntities($params);

        if (!empty($totalOnly)) {
            return ['total' => $result['total']];
        }

        if (isset($result['collection'])) {
            $list = $result['collection']->getValueMapList();
        } elseif (isset($result['list'])) {
            $list = $result['list'];
        } else {
            $list = [];
        }

        if (!empty($collectionOnly)) {
            return [
                'list' => $list,
            ];
        }

        return [
            'total' => $result['total'] ?? null,
            'list'  => $list,
        ];
    }
}

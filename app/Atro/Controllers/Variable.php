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

use Espo\Core\Controllers\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class Variable extends Base
{
    public function actionList($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        return $this->getRecordService()->findEntities($params);
    }

    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return $this->getRecordService()->createEntity($data);
    }

    public function actionRead($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return $this->getRecordService()->readEntity($params['id']);
    }

    public function actionPatch($params, $data, $request)
    {
        if (!$request->isPatch()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return $this->getRecordService()->updateEntity($params['id'], $data);
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return $this->getRecordService()->deleteEntity($params['id']);
    }

    protected function getRecordService(): \Espo\Services\Variable
    {
        return $this->getService('Variable');
    }
}

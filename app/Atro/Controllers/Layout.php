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

namespace Atro\Controllers;

use Atro\Core\DataManager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\LayoutManager;

class Layout extends AbstractRecordController
{
    public function actionGetContent($params, $data, $request)
    {
        $data = $this->getLayoutManager()->get($params['scope'], $params['viewType'],
            $request->get('relatedScope') ?? null, $request->get('layoutProfileId') ?? null,
            $request->get('isAdminPage') === 'true');

        if (empty($data)) {
            throw new NotFound("Layout " . $params['scope'] . ":" . $params['viewType'] . ' is not found.');
        }

        return $data;
    }

    public function actionUpdateContent($params, $data, $request)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        $layoutProfileId = (string)$request->get('layoutProfileId');
        $relatedEntity = (string)$request->get('relatedScope');

        if ((!$request->isPut() && !$request->isPatch()) || empty($layoutProfileId)) {
            throw new BadRequest();
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);
        $result = $layoutManager->save($params['scope'], $params['viewType'], $relatedEntity, $layoutProfileId, json_decode(json_encode($data), true));

        if ($result === false) {
            throw new Error("Error while saving layout.");
        }

        $this->getDataManager()->clearCache(true);

        return $layoutManager->get($params['scope'], $params['viewType'], $relatedEntity, $layoutProfileId);
    }

    public function actionResetToDefault($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->scope) || empty($data->viewType) || empty($data->layoutProfileId)) {
            throw new BadRequest();
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile((string)$data->layoutProfileId);
        return $layoutManager->resetToDefault((string)$data->scope, (string)$data->viewType, (string)$data->relatedScope, (string)$data->layoutProfileId);
    }

    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return bool
     * @throws BadRequest
     */
    public function actionResetAllToDefault($params, $data, $request): bool
    {
        $layoutProfileId = (string)$request->get('layoutProfileId');

        if (!$request->isPost() || empty($layoutProfileId)) {
            throw new BadRequest();
        }

        $layoutManager = $this->getLayoutManager();
        $layoutManager->checkLayoutProfile($layoutProfileId);
        return $layoutManager->resetAllToDefault($layoutProfileId);
    }

    public function actionSavePreference($params, $data, $request): bool
    {
        if (!$request->isPost() || empty($data->scope) || empty($data->viewType)) {
            throw new BadRequest();
        }

        $relatedScope = null;
        $layoutProfileId = null;

        if (!empty($data->relatedScope)) {
            $relatedScope = (string)$data->relatedScope;
        }
        if (!empty($data->layoutProfileId)) {
            $layoutProfileId = (string)$data->layoutProfileId;
        }

        $layoutManager = $this->getLayoutManager();
        return $layoutManager->saveUserPreference((string)$data->scope, (string)$data->viewType, $relatedScope, $layoutProfileId);
    }


    public function getLayoutManager(): LayoutManager
    {
        return $this->getContainer()->get('layoutManager');
    }

    public function getDataManager(): DataManager
    {
        return $this->getContainer()->get('dataManager');
    }
}

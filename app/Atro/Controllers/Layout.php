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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;

class Layout extends AbstractRecordController
{
    public function actionGetContent($params, $data, $request)
    {
        $data = $this->getContainer()->get('layout')->get($params['scope'], $params['name'],
            $request->get('relatedScope') ?? null, $request->get('layoutProfileId') ?? null,
            $request->get('isAdminPage') === 'true');
        if (empty($data)) {
            throw new NotFound("Layout " . $params['scope'] . ":" . $params['name'] . ' is not found.');
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

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile($layoutProfileId);
        $result = $layoutManager->save($params['scope'], $params['name'], $relatedEntity, $layoutProfileId, json_decode(json_encode($data), true));

        if ($result === false) {
            throw new Error("Error while saving layout.");
        }

        $this->getContainer()->get('dataManager')->clearCache();

        return $layoutManager->get($params['scope'], $params['name'], $relatedEntity, $layoutProfileId);
    }

    public function actionResetToDefault($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->scope) || empty($data->name) || empty($data->layoutProfileId)) {
            throw new BadRequest();
        }

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile((string)$data->layoutProfileId);
        return $layoutManager->resetToDefault((string)$data->scope, (string)$data->name, (string)$data->relatedScope, (string)$data->layoutProfileId);
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

        /** @var \Atro\Core\Utils\Layout $layoutManager */
        $layoutManager = $this->getContainer()->get('layout');
        $layoutManager->checkLayoutProfile($layoutProfileId);
        return $layoutManager->resetAllToDefault($layoutProfileId);
    }
}

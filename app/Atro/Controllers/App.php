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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\RealtimeManager;

class App extends \Espo\Controllers\App
{
    public function postActionLogNavigation($params, $data, $request)
    {
        if (!$request->isPost() || empty($params['id'])) {
            throw new BadRequest();
        }

        return true;
    }
    public function postActionStartEntityListening($params, $data, $request)
    {
        if (empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        return $this->getRealtimeManager()->startEntityListening($data->entityName, $data->entityId);
    }

    public function postActionPrepareScriptFields($params, $data, $request)
    {
        if (empty($data->entityName) && (empty($data->fields) || !is_array($data->fields))) {
            throw new BadRequest();
        }

        return $this->getService('App')->prepareScriptFields($data->entityName, $data->fields);
    }

    public function postActionPrepareScriptAttributes($params, $data, $request)
    {
        if (empty($data->entityName) && (empty($data->attributesIds) || !is_array($data->attributesIds))) {
            throw new BadRequest();
        }

        return $this->getService('App')->prepareScriptAttributes($data->entityName, $data->attributesIds);
    }

    public function actionDefaultValueForScriptType($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new NotFound();
        }

        if (empty($request->get('entityName')) || empty($request->get('field'))) {
            throw new BadRequest("'entityName' and 'field' params are required.");
        }

        $default = null;

        $defaultValueType = $this
            ->getMetadata()
            ->get("entityDefs.{$request->get('entityName')}.fields.{$request->get('field')}.defaultValueType");

        if ($defaultValueType === 'script') {
            $script = $this
                ->getMetadata()
                ->get("entityDefs.{$request->get('entityName')}.fields.{$request->get('field')}.default");

            if (!empty($script)) {
                $default = $this->getContainer()->get('twig')->renderTemplate((string)$script, []);
            }
        }

        return [
            "default" => $default,
        ];
    }

    public function actionRecalculateScriptField($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $this->checkControllerAccess();

        return $this->getService('App')->recalculateScriptField($data)->getValueMap();
    }

    public function actionMerge($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->scope) || empty($data->sourceIds) || !is_array($data->sourceIds) || !($data->attributes instanceof \StdClass)) {
            throw new BadRequest();
        }

        $targetId = null;

        if(!empty($data->targetId)) {
            $targetId = $data->targetId;
        }

        $sourceIds = $data->sourceIds;
        $attributes = $data->attributes;

        if (!$this->getAcl()->check($data->scope, 'create')) {
            throw new Forbidden();
        }

        $entity = $this->getService($data->scope)->merge($targetId, $sourceIds, $attributes);

        return $entity->getValueMap();
    }


    protected function getRealtimeManager(): RealtimeManager
    {
        return $this->getContainer()->get('realtimeManager');
    }
}

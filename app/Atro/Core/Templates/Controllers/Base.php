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
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Slim\Http\Request;

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

    public function actionGetAttributeValues($params, $data, Request $request): array
    {
        if (empty($this->getMetadata()->get("scopes.{$this->name}.hasAttribute"))) {
            throw new BadRequest();
        }

        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $id = $params['id'];

        if (!empty($entity = $this->getRecordService()->getEntity($id))) {
            return $entity->getAttributeValuesArray();
        }

        throw new Error();
    }

    public function actionAddAttributes($params, $data, Request $request): bool
    {
        if (empty($this->getMetadata()->get("scopes.{$this->name}.hasAttribute")) ||
            !empty($this->getMetadata()->get(['scopes', $data->entityName, 'disableAttributeLinking']))) {
            throw new BadRequest();
        }

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit') || !$this->getAcl()->check($this->name, 'createAttributeValue')) {
            throw new Forbidden();
        }


        $id = $params['id'];
        $attributesIds = $data->attributeIds ?? [];

        if (!is_array($attributesIds) || empty($attributesIds)) {
            throw new BadRequest();
        }

        $entity = $this->getRecordService()->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }


        return $this->getService('Attribute')->addAttributeValue($entity->getEntityName(), $entity->get('id'), null, $attributesIds);
    }

    public function actionUpsertAttributeValues($params, $data, Request $request): bool
    {
        if (empty($this->getMetadata()->get("scopes.{$this->name}.hasAttribute"))) {
            throw new BadRequest();
        }

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        $id = $params['id'];

        $attributeValues = $data->attributeValues ?? null;
        if (empty($attributeValues) || !is_array($attributeValues)) {
            throw new BadRequest();
        }

        $input = new \stdClass();
        $input->attributeValues = $attributeValues;

        $this->getRecordService()->updateEntity($id, $input);

        return true;
    }

    public function actionDeleteAttributeValues($params, $data, Request $request): array
    {
        if (empty($this->getMetadata()->get("scopes.{$this->name}.hasAttribute")) ||
            !empty($this->getMetadata()->get(['scopes', $data->entityName, 'disableAttributeLinking']))) {
            throw new BadRequest();
        }

        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit') || !$this->getAcl()->check($this->name, 'deleteAttributeValue')) {
            throw new Forbidden();
        }

        $id = $params['id'];

        $attributeIds = $data->attributeIds ?? [];
        if (!is_array($attributeIds) || empty($attributeIds)) {
            throw new BadRequest();
        }

        $entity = $this->getRecordService()->getEntity($id);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        return $this->getService('Attribute')->removeAttributeValues($entity->getEntityName(), $entity->get('id'), $attributeIds);
    }

}

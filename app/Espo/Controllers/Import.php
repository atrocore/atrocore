<?php

namespace Espo\Controllers;

use Espo\Core\Utils as Utils;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class Import extends \Espo\Core\Controllers\Record
{
    protected function checkControllerAccess()
    {
        if (!$this->getAcl()->check('Import')) {
            throw new Forbidden();
        }
    }

    public function actionPatch($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionUpdate($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionMassUpdate($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionCreateLink($params, $data, $request)
    {
        throw new BadRequest();
    }

    public function actionRemoveLink($params, $data, $request)
    {
        throw new BadRequest();
    }

    protected function getFileStorageManager()
    {
        return $this->getContainer()->get('fileStorageManager');
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    public function actionUploadFile($params, $data, $request)
    {
        $contents = $data;

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set('type', 'text/csv');
        $attachment->set('role', 'Import File');
        $attachment->set('name', 'import-file.csv');
        $attachment->set('contents', $contents);
        $this->getEntityManager()->saveEntity($attachment);

        return array(
            'attachmentId' => $attachment->id
        );
    }

    public function actionRevert($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        return $this->getService('Import')->revert($data->id);
    }

    public function actionRemoveDuplicates($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        return $this->getService('Import')->removeDuplicates($data->id);
    }

    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost() && !$request->isPut()) {
            throw new BadRequest();
        }

        if (!isset($data->delimiter)) {
            throw new BadRequest();
        }

        if (!isset($data->textQualifier)) {
            throw new BadRequest();
        }

        if (!isset($data->dateFormat)) {
            throw new BadRequest();
        }

        if (!isset($data->timeFormat)) {
            throw new BadRequest();
        }

        if (!isset($data->personNameFormat)) {
            throw new BadRequest();
        }

        if (!isset($data->decimalMark)) {
            throw new BadRequest();
        }

        if (!isset($data->defaultValues)) {
            throw new BadRequest();
        }

        if (!isset($data->action)) {
            throw new BadRequest();
        }

        if (!isset($data->attachmentId)) {
            throw new BadRequest();
        }

        if (!isset($data->entityType)) {
            throw new BadRequest();
        }

        if (!isset($data->attributeList)) {
            throw new BadRequest();
        }

        $timezone = 'UTC';
        if (isset($data->timezone)) {
           $timezone = $data->timezone;
        }

        $importParams = array(
            'headerRow' => !empty($data->headerRow),
            'delimiter' => $data->delimiter,
            'textQualifier' => $data->textQualifier,
            'dateFormat' => $data->dateFormat,
            'timeFormat' => $data->timeFormat,
            'timezone' => $timezone,
            'personNameFormat' => $data->personNameFormat,
            'decimalMark' => $data->decimalMark,
            'currency' => $data->currency,
            'defaultValues' => $data->defaultValues,
            'action' => $data->action,
            'skipDuplicateChecking' => !empty($data->skipDuplicateChecking),
            'idleMode' => !empty($data->idleMode)
        );

        if (property_exists($data, 'updateBy')) {
            $importParams['updateBy'] = $data->updateBy;
        }

        $attachmentId = $data->attachmentId;

        if (!$this->getAcl()->check($data->entityType, 'edit')) {
            throw new Forbidden();
        }

        return $this->getService('Import')->import($data->entityType, $data->attributeList, $attachmentId, $importParams);
    }

    public function postActionUnmarkAsDuplicate($params, $data)
    {
        if (empty($data->id) || empty($data->entityType) || empty($data->entityId)) {
            throw new BadRequest();
        }
        $this->getService('Import')->unmarkAsDuplicate($data->id, $data->entityType, $data->entityId);
        return true;
    }
}

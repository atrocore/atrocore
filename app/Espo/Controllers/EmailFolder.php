<?php

namespace Espo\Controllers;

use \Espo\Core\Exceptions\BadRequest;

class EmailFolder extends \Espo\Core\Controllers\Record
{
    public function postActionMoveUp($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }

        $this->getRecordService()->moveUp($data->id);

        return true;
    }

    public function postActionMoveDown($params, $data, $request)
    {
        if (empty($data->id)) {
            throw new BadRequest();
        }

        $this->getRecordService()->moveDown($data->id);

        return true;
    }

    public function getActionListAll()
    {
        return $this->getRecordService()->listAll();
    }
}

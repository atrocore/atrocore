<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

class ActionHistoryRecord extends \Espo\Core\ORM\Repositories\RDB
{
    protected $hooksDisabled = true;

    protected $processFieldsAfterSaveDisabled = true;

    protected $processFieldsBeforeSaveDisabled = true;

    protected $processFieldsAfterRemoveDisabled = true;
}

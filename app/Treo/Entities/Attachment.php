<?php

namespace Treo\Entities;

use Espo\Core\ORM\Entity as Base;

/**
 * Class Attachment
 *
 * @author r.ratsun@treolabs.com
 */
class Attachment extends Base
{
    /**
     * @return mixed|null
     */
    public function getSourceId()
    {
        $sourceId = $this->get('sourceId');
        if (!$sourceId) {
            $sourceId = $this->id;
        }

        return $sourceId;
    }

    /**
     * @return string
     */
    public function _getStorage()
    {
        return $this->valuesContainer['storage'] ? $this->valuesContainer['storage'] : "UploadDir";
    }
}

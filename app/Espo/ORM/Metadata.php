<?php

namespace Espo\ORM;

class Metadata
{
    protected $data = array();

    public function setData($data)
    {
        $this->data = $data;
    }

    public function get($entityType, $key = null, $default = null)
    {
        if (!array_key_exists($entityType, $this->data)) {
            return null;
        }
        $data = $this->data[$entityType];
        if (!$key) return $data;

        return \Espo\Core\Utils\Util::getValueByKey($data, $key, $default);
    }

    public function has($entityType)
    {
        if (!array_key_exists($entityType, $this->data)) {
            return null;
        }
        return true;
    }
}

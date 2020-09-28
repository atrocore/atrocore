<?php

namespace Espo\Entities;

class Integration extends \Espo\Core\ORM\Entity
{
    public function get($name, $params = array())
    {
        if ($name == 'id') {
            return $this->id;
        }

        if ($this->hasField($name)) {
            if (array_key_exists($name, $this->valuesContainer)) {
                return $this->valuesContainer[$name];
            }
        } else {
            if ($this->get('data')) {
                $data = $this->get('data');
            } else {
                $data = new \stdClass();
            }
            if (isset($data->$name)) {
                return $data->$name;
            }
        }
        return null;
    }

    public function clear($name = null)
    {
        parent::clear($name);

        $data = $this->get('data');
        if (empty($data)) {
            $data = new \stdClass();
        }
        unset($data->$name);
        $this->set('data', $data);
    }

    public function set($p1, $p2 = null)
    {
        if (is_object($p1)) {
            $p1 = get_object_vars($p1);
        }

        if (is_array($p1)) {
            if ($p2 === null) {
                $p2 = false;
            }
            $this->populateFromArray($p1, $p2);
            return;
        }

        $name = $p1;
        $value = $p2;

        if ($name == 'id') {
            $this->id = $value;
            return;
        }

        if ($this->hasField($name)) {
            $this->valuesContainer[$name] = $value;
        } else {
            $data = $this->get('data');
            if (empty($data)) {
                $data = new \stdClass();
            }
            $data->$name = $value;
            $this->set('data', $data);
        }
    }

    public function populateFromArray(array $arr, $onlyAccessible = true, $reset = false)
    {
        if ($reset) {
            $this->reset();
        }

        foreach ($arr as $field => $value) {
            if (is_string($field)) {

                if ($this->hasField($field)) {
                    $fields = $this->getFields();
                    $fieldDefs = $fields[$field];

                    if (!is_null($value)) {
                        switch ($fieldDefs['type']) {
                            case self::VARCHAR:
                                break;
                            case self::BOOL:
                                $value = ($value === 'true' || $value === '1' || $value === true);
                                break;
                            case self::INT:
                                $value = intval($value);
                                break;
                            case self::FLOAT:
                                $value = floatval($value);
                                break;
                            case self::JSON_ARRAY:
                                $value = is_string($value) ? json_decode($value) : $value;
                                if (!is_array($value)) {
                                    $value = null;
                                }
                                break;
                            case self::JSON_OBJECT:
                                $value = is_string($value) ? json_decode($value) : $value;
                                if (!($value instanceof \stdClass) && !is_array($value)) {
                                    $value = null;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }

                $this->set($field, $value);
            }
        }
    }

    public function toArray()
    {
        $arr = array();
        if (isset($this->id)) {
            $arr['id'] = $this->id;
        }
        foreach ($this->fields as $field => $defs) {
            if ($field == 'id') {
                continue;
            }
            if ($field == 'data') {
                continue;
            }
            if ($this->has($field)) {
                $arr[$field] = $this->get($field);
            }
        }

        $data = $this->get('data');
        if (empty($data)) {
            $data = new \stdClass();
        }

        $dataArr = get_object_vars($data);

        $arr = array_merge($arr, $dataArr);
        return $arr;
    }

    public function getValueMap()
    {
        $arr = $this->toArray();

        return (object) $arr;
    }
}

<?php

namespace Espo\Core\Utils\Database\DBAL\Schema;

class Column extends \Doctrine\DBAL\Schema\Column
{

    /**
     * @var boolean
     */
    protected $_notnull = false;

    /**
     * @var boolean
     */
    protected $_unique = false;


    /**
     * @param boolean $unique
     *
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function setUnique($unique)
    {
        $this->_unique = (bool)$unique;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getUnique()
    {
        return $this->_unique;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_merge(array(
            'name'          => $this->_name,
            'type'          => $this->_type,
            'default'       => $this->_default,
            'notnull'       => $this->_notnull,
            'length'        => $this->_length,
            'precision'     => $this->_precision,
            'scale'         => $this->_scale,
            'fixed'         => $this->_fixed,
            'unsigned'      => $this->_unsigned,
            'autoincrement' => $this->_autoincrement,
            'unique' => $this->_unique,
            'columnDefinition' => $this->_columnDefinition,
            'comment' => $this->_comment,
        ), $this->_platformOptions, $this->_customSchemaOptions);
    }

}
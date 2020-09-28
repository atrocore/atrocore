<?php

namespace Espo\Core\Utils\Api\Slim;

class Environment extends \Slim\Environment
{
    /**
     * Define undefined $_SERVER variables
     */
    private static function setUndefinedVariables()
    {
        $list = array(
            'REQUEST_METHOD',
            'REMOTE_ADDR',
            'SERVER_NAME',
            'SERVER_PORT',
            'REQUEST_URI',
        );

        foreach ($list as $name) {
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = '';
            }
        }
    }

    public static function getInstance($refresh = false)
    {
        static::setUndefinedVariables();

        return parent::getInstance($refresh);
    }
}

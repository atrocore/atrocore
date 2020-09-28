<?php

namespace Espo\Core\Utils;

class Json
{
    /**
     * JSON encode a string
     *
     * @param mixed $value
     * @param int $options Default 0
     * @return string
     */
    public static function encode($value, $options = 0)
    {
        $json = json_encode($value, $options);

        $error = self::getLastError();
        if ($json === null || !empty($error)) {
            $GLOBALS['log']->error('Json::encode():' . $error . ' - ' . print_r($value, true));
        }

        return $json;
    }

    /**
     * JSON decode a string (Fixed problem with "\")
     *
     * @param string $json
     * @param bool $assoc Default false
     * @return object|array
     */
    public static function decode($json, $assoc = false)
    {
        if (is_null($json) || $json === false) {
            return $json;
        }

        if (is_array($json)) {
            $GLOBALS['log']->warning('Json::decode() - JSON cannot be decoded - '.$json);
            return false;
        }

        $json = json_decode($json, $assoc);

        $error = self::getLastError();
        if ($error) {
            $GLOBALS['log']->error('Json::decode():' . $error);
        }

        return $json;
    }

    /**
     * Check if the string is JSON
     *
     * @param string $json
     * @return bool
     */
    public static function isJSON($json)
    {
        if ($json === '[]' || $json === '{}') {
            return true;
        } else if (is_array($json)) {
            return false;
        }

        return static::decode($json) != null;
    }

    /**
    * Get an array data (if JSON convert to array)
    *
    * @param mixed $data - can be JSON, array
    *
    * @return array
    */
    public static function getArrayData($data, $returns = array())
    {
        if (is_array($data)) {
            return $data;
        }
        else if (static::isJSON($data)) {
            return static::decode($data, true);
        }

        return $returns;
    }

    protected static function getLastError()
    {
        $error = json_last_error();

        if (!empty($error)) {
            return json_last_error_msg();
        }
    }
}
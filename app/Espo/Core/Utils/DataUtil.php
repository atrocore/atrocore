<?php

namespace Espo\Core\Utils;

use \Espo\Core\Exceptions\Error;

class DataUtil
{
    public static function unsetByKey(&$data, $unsetList, $removeEmptyItems = false)
    {
        if (empty($unsetList)) {
            return $data;
        }
        if (is_string($unsetList)) {
            $unsetList = array($unsetList);
        }

        foreach ($unsetList as $unsetItem) {
            if (is_array($unsetItem)) {
                $arr = $unsetItem;
            } else if (is_string($unsetItem)) {
                $arr = explode('.', $unsetItem);
            } else {
                throw new Error('Bad unset parameter');
            }

            $pointer = &$data;
            $parent = null;
            $elementArr = [];
            $elementArr[] = &$pointer;
            foreach ($arr as $i => $key) {
                if ($i === count($arr) - 1) {
                    if (is_array($pointer)) {
                        if (array_key_exists($key, $pointer)) {
                            unset($pointer[$key]);
                        }
                    } else if (is_object($pointer)) {
                        unset($pointer->$key);

                        if ($removeEmptyItems) {
                            for ($j = count($elementArr); $j > 0; $j--) {
                                $pointerBack =& $elementArr[$j];
                                if (is_object($pointerBack) && count(get_object_vars($pointerBack)) === 0) {
                                    $previous =& $elementArr[$j - 1];
                                    if (is_object($previous)) {
                                        $key = $arr[$j - 1];
                                        unset($previous->$key);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $parent = $pointer;
                    if (is_array($pointer)) {
                        $pointer = &$pointer[$key];
                    } else if (is_object($pointer)) {
                        $pointer = &$pointer->$key;
                    }
                    $elementArr[] = &$pointer;
                }
            }
        }

        return $data;
    }

    public static function unsetByValue(&$data, $needle)
    {
        if (is_object($data)) {
            foreach (get_object_vars($data) as $key => $value) {
                self::unsetByValue($data->$key, $needle);

                if ($data->$key === $needle) {
                    unset($data->$key);
                }
            }
        } else if (is_array($data)) {
            $doReindex = false;
            foreach ($data as $key => $value) {
                self::unsetByValue($data[$key], $needle);

                if ($data[$key] === $needle) {
                    unset($data[$key]);
                    $doReindex = true;
                }
            }
            if ($doReindex) {
                $data = array_values($data);
            }
        }

        return $data;
    }

    public static function merge($data, $overrideData)
    {
        $appendIdentifier = '__APPEND__';

        if (empty($data) && empty($overrideData)) {
            if (is_object($data) || is_object($overrideData)) {
                return (object) [];
            } else if (is_array($data) || is_array($overrideData)) {
                return [];
            } else {
                return $overrideData;
            }
        }

        if (is_object($overrideData)) {
            if (empty($data)) {
                $data = (object) [];
            }
            foreach (get_object_vars($overrideData) as $key => $value) {
                if (isset($data->$key)) {
                    $data->$key = self::merge($data->$key, $overrideData->$key);
                } else {
                    $data->$key = $overrideData->$key;
                    self::unsetByValue($data->$key, $appendIdentifier);
                }
            }
            return $data;
        } else if (is_array($overrideData)) {
            if (empty($data)) {
                $data = [];
            }
            if (in_array($appendIdentifier, $overrideData)) {
                foreach ($overrideData as $key => $item) {
                    if ($item === $appendIdentifier) {
                        continue;
                    }
                    $data[] = $item;
                }
            } else {
                $data = $overrideData;
            }
            return $data;
        } else {
            return $overrideData;
        }
    }
}


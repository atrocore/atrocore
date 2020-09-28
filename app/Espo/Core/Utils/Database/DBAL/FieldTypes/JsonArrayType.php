<?php

namespace Espo\Core\Utils\Database\DBAL\FieldTypes;

class JsonArrayType extends \Doctrine\DBAL\Types\JsonArrayType
{
    const JSON_ARRAY = 'jsonArray';

    public function getName()
    {
        return self::JSON_ARRAY;
    }

    public static function getDbTypeName()
    {
        return 'TEXT';
    }

}

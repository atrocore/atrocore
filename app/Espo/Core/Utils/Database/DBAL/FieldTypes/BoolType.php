<?php

namespace Espo\Core\Utils\Database\DBAL\FieldTypes;

use Doctrine\DBAL\Types\BooleanType;

class BoolType extends BooleanType
{
    const BOOL = 'bool';

    public function getName()
    {
        return self::BOOL;
    }

    public static function getDbTypeName()
    {
        return 'TINYINT';
    }
}
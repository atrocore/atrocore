<?php

namespace Espo\Core\Utils\Database\DBAL\FieldTypes;

use Doctrine\DBAL\Types\StringType;

class PasswordType extends StringType
{
    const PASSWORD = 'password';

    public function getName()
    {
        return self::PASSWORD;
    }

    public static function getDbTypeName()
    {
        return 'VARCHAR';
    }
}


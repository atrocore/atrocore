<?php

namespace Espo\Core\Utils\Database\DBAL\FieldTypes;

use Doctrine\DBAL\Types\IntegerType;

class IntType extends IntegerType
{
    const INTtype = 'int';

    public function getName()
    {
        return self::INTtype;
    }
}
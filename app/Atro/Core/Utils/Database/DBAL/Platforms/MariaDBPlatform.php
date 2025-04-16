<?php

namespace Atro\Core\Utils\Database\DBAL\Platforms;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;

class MariaDBPlatform extends MariaDb1027Platform
{
    protected function getReservedKeywordsClass(): string
    {
        return Keywords\MariaDbKeywords::class;
    }
}
<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Utils\Database\DBAL\Schema;

class PostgreSQLSchemaManager extends \Doctrine\DBAL\Schema\PostgreSQLSchemaManager
{
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);

        if (isset($tableColumn['default']) && preg_match("/^nextval\('(.*)'(::.*)?\)$/", $tableColumn['default'], $matches) === 1) {
            $column->setAutoincrement(false);
            $column->setDefault("nextval('{$matches[1]}')");
        }

        return $column;
    }
}
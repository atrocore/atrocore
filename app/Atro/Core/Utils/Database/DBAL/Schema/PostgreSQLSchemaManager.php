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
    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $ginIndexNames = [];
        foreach ($tableIndexes as $row) {
            if (($row['amname'] ?? '') === 'gin') {
                $ginIndexNames[$row['relname']] = true;
            }
        }

        $buffer = [];
        foreach ($tableIndexes as $row) {
            $colNumbers    = array_map('intval', explode(' ', $row['indkey']));
            $columnNameSql = sprintf(
                'SELECT attnum, attname FROM pg_attribute WHERE attrelid=%d AND attnum IN (%s) ORDER BY attnum ASC',
                $row['indrelid'],
                implode(' ,', $colNumbers)
            );

            $indexColumns = $this->_conn->fetchAllAssociative($columnNameSql);

            foreach ($colNumbers as $colNum) {
                foreach ($indexColumns as $colRow) {
                    if ($colNum !== (int)$colRow['attnum']) {
                        continue;
                    }

                    $entry = [
                        'key_name'    => $row['relname'],
                        'column_name' => trim($colRow['attname']),
                        'non_unique'  => !$row['indisunique'],
                        'primary'     => $row['indisprimary'],
                        'where'       => $row['where'],
                    ];

                    if (isset($ginIndexNames[$row['relname']])) {
                        $entry['flags'] = ['gin'];
                    }

                    $buffer[] = $entry;
                }
            }
        }

        return \Doctrine\DBAL\Schema\AbstractSchemaManager::_getPortableTableIndexesList($buffer, $tableName);
    }

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
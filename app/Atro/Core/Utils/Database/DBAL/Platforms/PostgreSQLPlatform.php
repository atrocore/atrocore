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

declare(strict_types=1);

namespace Atro\Core\Utils\Database\DBAL\Platforms;

use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

class PostgreSQLPlatform extends \Doctrine\DBAL\Platforms\PostgreSQL100Platform
{
    public function getListTableIndexesSQL($table, $database = null): string
    {
        $baseSQL = parent::getListTableIndexesSQL($table, $database);

        // Inject pg_am join to expose the index access method name (e.g. 'gin', 'btree')
        return str_replace(
            'FROM pg_class, pg_index',
            'FROM pg_class JOIN pg_am am ON pg_class.relam = am.oid, pg_index',
            str_replace(
                'pg_get_expr(indpred, indrelid) AS where',
                'pg_get_expr(indpred, indrelid) AS where, am.amname',
                str_replace('pg_index.indexrelid = oid', 'pg_index.indexrelid = pg_class.oid',
                    str_replace('WHERE oid IN', 'WHERE pg_class.oid IN', $baseSQL))
            )
        );
    }

    public function getCreateIndexSQL(Index $index, $table): string
    {
        if ($index->hasFlag('gin')) {
            if ($table instanceof Table) {
                $table = $table->getQuotedName($this);
            }

            $name = $index->getQuotedName($this);
            $columns = implode(', ', array_map(
                fn(string $col) => $this->quoteIdentifier($col) . ' gin_trgm_ops',
                $index->getColumns()
            ));

            return 'CREATE INDEX ' . $name . ' ON ' . $table . ' USING GIN (' . $columns . ')';
        }

        return parent::getCreateIndexSQL($index, $table);
    }
}

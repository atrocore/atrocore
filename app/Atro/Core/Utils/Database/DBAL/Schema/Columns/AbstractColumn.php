<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Utils\Database\DBAL\Schema\Columns;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Espo\Core\Utils\Util;

abstract class AbstractColumn implements ColumnInterface
{
    protected string $fieldName;
    protected array $fieldDefs = [];
    protected Connection $connection;

    public function __construct(string $fieldName, array $fieldDefs, Connection $connection)
    {
        $this->fieldName = $fieldName;
        $this->fieldDefs = $fieldDefs;
        $this->connection = $connection;
    }

    public function add(Table $table, Schema $schema): void
    {
        $table->addColumn($this->getColumnName(), $this->fieldDefs['type'], $this->getColumnParameters());
    }

    public function getColumnName(): string
    {
        return Util::toUnderScore($this->fieldName);
    }

    public function getColumnParameters(): array
    {
        $result = [];

        $result['notnull'] = !empty($this->fieldDefs['notNull']);

        if (isset($this->fieldDefs['default'])) {
            $result['default'] = $this->fieldDefs['default'];
        }

        return $result;
    }

    protected function isPgSQL(): bool
    {
        return strpos(get_class($this->connection->getDriver()), 'PgSQL') !== false;
    }
}
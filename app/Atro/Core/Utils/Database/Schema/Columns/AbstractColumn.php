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

namespace Atro\Core\Utils\Database\Schema\Columns;

use Doctrine\DBAL\Schema\Table;
use Espo\Core\Utils\Util;

abstract class AbstractColumn implements ColumnInterface
{
    protected string $fieldName;
    protected array $fieldDefs = [];
    protected array $columnParams = [];

    public function __construct(string $fieldName, array $fieldDefs)
    {
        $this->fieldName = $fieldName;
        $this->fieldDefs = $fieldDefs;
    }

    public function add(Table $table): void
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

        foreach ($this->columnParams as $name => $dbalName) {
            if (isset($this->fieldDefs[$name])) {
                $result[$dbalName] = $this->fieldDefs[$name];
            }
        }

        return $result;
    }
}
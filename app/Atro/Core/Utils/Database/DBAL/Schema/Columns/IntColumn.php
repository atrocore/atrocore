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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

class IntColumn extends AbstractColumn
{
    public function add(Table $table, Schema $schema): void
    {
        $columnParameters = $this->getColumnParameters();
        if (!empty($this->fieldDefs['autoincrement'])) {
            if (!$this->isPgSQL()) {
                $columnParameters['autoincrement'] = true;
            } else {
                $sequence = "{$table->getName()}_{$this->getColumnName()}_seq";
                $schema->createSequence($sequence);
                $columnParameters['default'] = "nextval('$sequence')";
            }
            $columnParameters['notnull'] = true;
        }

        $table->addColumn($this->getColumnName(), 'integer', $columnParameters);

        if (!empty($this->fieldDefs['autoincrement'])) {
            $table->addUniqueIndex([$this->getColumnName()]);
        }
    }
}
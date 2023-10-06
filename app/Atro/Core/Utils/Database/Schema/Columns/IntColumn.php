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

class IntColumn extends AbstractColumn
{
    protected array $columnParams
        = [
            'notNull'       => 'notnull',
            'default'       => 'default',
            'autoincrement' => 'autoincrement'
        ];

    public function add(Table $table): void
    {
        $table->addColumn($this->getColumnName(), 'integer', $this->getColumnParameters());
        if (!empty($this->fieldDefs['autoincrement'])) {
            $table->addUniqueIndex([$this->getColumnName()]);
        }
    }
}
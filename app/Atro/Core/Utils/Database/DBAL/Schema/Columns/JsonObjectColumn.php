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

class JsonObjectColumn extends AbstractColumn
{
    public function add(Table $table, Schema $schema): void
    {
        $table->addColumn($this->getColumnName(), 'json', $this->getColumnParameters());
    }

    public function getColumnParameters(): array
    {
        $result = [];

        $result['notnull'] = !empty($this->fieldDefs['notNull']);

        return $result;
    }
}
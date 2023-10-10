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

namespace Atro\Core\Utils\Database\DBAL\Schema\FieldTypes;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

class VarcharType extends AbstractType
{
    public function add(Table $table, Schema $schema): void
    {
        $table->addColumn($this->getColumnName(), 'string', $this->getColumnParameters());
    }

    public function getColumnParameters(): array
    {
        $result = parent::getColumnParameters();

        if (isset($this->fieldDefs['len'])) {
            $result['length'] = $this->fieldDefs['len'];
        }

        if (isset($this->fieldDefs['length'])) {
            $result['length'] = $this->fieldDefs['length'];
        }

        return $result;
    }
}
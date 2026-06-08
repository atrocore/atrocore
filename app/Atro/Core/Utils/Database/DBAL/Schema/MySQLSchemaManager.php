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

namespace Atro\Core\Utils\Database\DBAL\Schema;

class MySQLSchemaManager extends \Doctrine\DBAL\Schema\MySQLSchemaManager
{
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $column = parent::_getPortableTableColumnDefinition($tableColumn);

        // MySQL 8+ aliases utf8 as utf8mb3 internally, normalize for consistent comparison
        if ($column->hasPlatformOption('collation')) {
            $normalized = str_replace('utf8mb3_', 'utf8_', $column->getPlatformOption('collation'));
            $column->setPlatformOption('collation', $normalized);
        }

        return $column;
    }
}

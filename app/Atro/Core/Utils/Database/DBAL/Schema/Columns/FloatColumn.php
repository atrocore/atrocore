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

class FloatColumn extends AbstractColumn
{
    protected array $columnParams
        = [
            'notNull' => 'notnull',
            'default' => 'default'
        ];
}
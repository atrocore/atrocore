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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot8Dot10 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'address', 'contact_name', ['type' => 'string', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'address', 'contact_name');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        };
    }
}

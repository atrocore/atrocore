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
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Espo\Jobs\UpdateCurrencyExchangeViaECB;

class V1Dot8Dot7 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'account', 'language', ['type' => 'string', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'account', 'language');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        };
    }

}

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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Config;
use Atro\Core\Utils\Util;

class V1Dot10Dot42 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-17 13:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('note'))
            ->set('deleted', ':deleted')
            ->where('type = :create')
            ->setParameter('deleted', true, ParameterType::BOOLEAN)
            ->setParameter('create', 'Create')
            ->executeStatement();
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

}

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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-16 00:00:00');
    }

    public function up(): void
    {
       $this->getConnection()
           ->createQueryBuilder()
           ->update('account')
           ->set('language',':enUS')
           ->where('language=:en')
           ->setParameter('en','en')
           ->setParameter('enUS','en_US')
           ->executeQuery();

       $this->getConnection()
           ->createQueryBuilder()
           ->update('account')
           ->set('language',':deDE')
           ->where('language=:de')
           ->setParameter('de','de')
           ->setParameter('deDE','de_DE')
           ->executeQuery();
    }

    public function down(): void
    {
        $this->getConnection()
            ->createQueryBuilder()
            ->update('account')
            ->set('language',':en')
            ->where('language=:enUS')
            ->setParameter('en','en')
            ->setParameter('enUS','en_US')
            ->executeQuery();

        $this->getConnection()
            ->createQueryBuilder()
            ->update('account')
            ->set('language',':de')
            ->where('language=:deDE')
            ->setParameter('de','de')
            ->setParameter('deDE','de_DE')
            ->executeQuery();
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}

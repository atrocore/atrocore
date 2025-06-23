<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-03 17:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update("scheduled_job")
            ->set('is_active', ':false')
            ->where('type=:type')
            ->andWhere('is_active = :true')
            ->setParameter('type', 'ComposerAutoUpdate')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeQuery();
    }
}

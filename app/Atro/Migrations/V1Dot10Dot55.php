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

class V1Dot10Dot55 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-07 15:00:00');
    }

    public function up(): void
    {
        try {
            $preferences = $this->getConnection()->createQueryBuilder()
                ->select('id', 'data')
                ->from('preferences')
                ->fetchAllAssociative();

            foreach ($preferences as $preference) {
                $data = @json_decode($preference['data'], true);
                if (empty($data)) {
                    continue;
                }
                $data['id'] = $preference['id'];

                $this->getConnection()->createQueryBuilder()
                    ->update('preferences')
                    ->set('data', ':data')
                    ->where('id= :id')
                    ->setParameter('data', json_encode($data))
                    ->setParameter('id', $preference['id'])
                    ->executeStatement();

            }
        } catch (\Throwable $e) {
        }

        try {
            $this->getConnection()->createQueryBuilder()
                ->update('notification_rule')
                ->set('as_team_member', ':false')
                ->where('occurrence = :occurrence')
                ->andWhere('entity = :entity')
                ->setParameter('occurrence', 'updating')
                ->setParameter('entity', '')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeStatement();
        }catch (\Throwable $e){

        }
    }

    public function down(): void
    {
    }

}
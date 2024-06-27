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
use Espo\Core\Utils\Util;

class V1Dot10Dot36 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-06-27 10:00:00');
    }

    public function up(): void
    {
        $smtpConnection = $this->getConnection()->createQueryBuilder()
            ->from($this->getConnection()->quoteIdentifier('connection'), 'c')
            ->select('id')
            ->where('c.type = :type')
            ->andWhere('c.deleted = :deleted')
            ->setParameter('type', 'smtp')
            ->setParameter('deleted', false, ParameterType::BOOLEAN)
            ->fetchOne();

        if (empty($smtpConnection)) {
            $config = $this->getConfig();
            $id = Util::generateId();
            $name = 'Default SMTP';
            $this->getConnection()->createQueryBuilder()
                ->insert($this->getConnection()->quoteIdentifier('connection'))
                ->values([
                    'id'     => ':id',
                    'type'   => ':type',
                    'name'   => ':name',
                    'active' => ':active',
                    'data'   => ':data'
                ])
                ->setParameter('id', $id)
                ->setparameter('name', $name)
                ->setparameter('type', 'smtp')
                ->setparameter('active', !empty($config->get('disableEmailDelivery')), ParameterType::BOOLEAN)
                ->setparameter('data', json_encode([
                    'smtpServer'               => $config->get('smtpServer'),
                    'smtpPassword'             => $config->get('smtpPassword'),
                    'smtpPort'                 => $config->get('smtpPort'),
                    'smtpUsername'             => $config->get('smtpUsername'),
                    'outboundEmailFromName'    => $config->get('outboundEmailFromName'),
                    'outboundEmailFromAddress' => $config->get('outboundEmailFromAddress'),
                ]))
                ->executeStatement();

            $config->remove('smtpServer');
            $config->remove('smtpPassword');
            $config->remove('smtpPort');
            $config->remove('smtpUsername');
            $config->remove('outboundEmailFromAddress');
            $config->remove('outboundEmailFromName');
            $config->remove('disableEmailDelivery');
            $config->set('notificationSmtpConnectionId', $id);
            $config->set('notificationSmtpConnectionName', $name);

            $config->save();
        }
    }

    public function down(): void
    {
    }
}

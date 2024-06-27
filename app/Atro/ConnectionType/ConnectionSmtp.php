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

namespace Atro\ConnectionType;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Mail\Sender;
use Espo\ORM\Entity;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

class ConnectionSmtp extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connectionEntity): \Symfony\Component\Mailer\Transport\TransportInterface
    {
        $factory = new EsmtpTransportFactory;

        $scheme = in_array($connectionEntity->get('smtpSecurity'), ['SSL', 'TLS']) ? ($connectionEntity->get('smtpPort') === 465 ? 'smtps' : 'smtp') : '';
        return $factory->create(new Dsn(
            $scheme,
            $connectionEntity->get('smtpServer') ?? '',
            $connectionEntity->get('smtpUsername') ?? null,
            $connectionEntity->get('smtpPassword') ? $this->decryptPassword($connectionEntity->get('smtpPassword')) : null,
            $connectionEntity->get('smtpPort') ?? null,
        ));
    }


    public function sendTestEmail(Entity $connectionEntity, string $toEmail): bool
    {
        /* @var $sender Sender */
        $sender = $this->container->get('mailSender');
        $sender->send([
            'subject' => 'Test Email',
            'body'    => 'Test Email',
            'isHtml'  => false,
            'to'      => $toEmail
        ],
            $connectionEntity
        );
        return true;
    }
}

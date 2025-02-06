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
use Atro\Core\Mail\SmtpOauthAuthenticator;
use Atro\Core\Utils\Config;
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

class ConnectionSmtp extends AbstractConnection implements ConnectionInterface
{
    public function connect(Entity $connectionEntity): TransportInterface
    {
        $factory = new EsmtpTransportFactory;
        $authType = $connectionEntity->get('smtpAuthType');

        if (empty($authType) || $authType == 'basic') {
            $scheme = in_array($connectionEntity->get('smtpSecurity'), ['SSL', 'TLS']) ? ($connectionEntity->get('smtpPort') === 465 ? 'smtps' : 'smtp') : '';
            return $factory->create(new Dsn(
                $scheme,
                $connectionEntity->get('smtpServer') ?? '',
                $connectionEntity->get('smtpUsername') ?? null,
                $connectionEntity->get('smtpPassword') ? $this->decryptPassword($connectionEntity->get('smtpPassword')) : null,
                $connectionEntity->get('smtpPort') ?? null,
            ));
        }

        $transport = $factory->create(new Dsn(
            'smtp',
            $connectionEntity->get('smtpServer') ?? '',
            $connectionEntity->get('smtpUsername') ?? null,
            null,
            $connectionEntity->get('smtpPort') ?? null,
        ));

        $transport->setAuthenticators([new SmtpOauthAuthenticator(fn() => $this->getToken($connectionEntity))]);
        return $transport;
    }

    private function getToken(Entity $connectionEntity): string
    {
        $data = $this->decryptPassword($connectionEntity->get('smtpAccessData') ?? '');
        if (!empty($data)) {
            $data = @json_decode($data, true);
        }

        if (empty($data)) {
            throw new BadRequest('You need to authenticate this connection');
        }

        if (!empty($data['expires_at']) && (time() < $data['expires_at'])) {
            return $data['access_token'];
        }

        // fetch new token with refresh token
        $params = [
            'client_id'     => $connectionEntity->get('smtpClientId'),
            'scope'         => $connectionEntity->get('smtpOauthScope'),
            'client_secret' => $this->decryptPassword($connectionEntity->get('smtpClientSecret') ?? ''),
            'refresh_token' => $data['refresh_token'],
            'grant_type'    => 'refresh_token',
        ];


        try {
            $data = $this->fetchAccessToken($params, $connectionEntity);
        } catch (\Throwable $exception) {
            // clear access data if refresh token did not work
            $connectionEntity->set('smtpAccessData', null);
            $this->getEntityManager()->saveEntity($connectionEntity);
            throw $exception;
        }


        return $data['access_token'];
    }

    public function createAccessTokenFromAuthCode(Entity $connectionEntity, $authCode): array
    {
        $params = [
            'client_id'     => $connectionEntity->get('smtpClientId'),
            'client_secret' => $this->decryptPassword($connectionEntity->get('smtpClientSecret') ?? ''),
            'scope'         => $connectionEntity->get('smtpOauthScope'),
            'code'          => $authCode,
//            'redirect_uri' => $this->getConfig()->get('siteUrl') . '/?entryPoint=OauthSmtpCallback',
            'redirect_uri'  => 'https://atrocore.local/?entryPoint=OauthSmtpCallback',
            'grant_type'    => 'authorization_code',
        ];

        return $this->fetchAccessToken($params, $connectionEntity);
    }

    private function fetchAccessToken(array $params, Entity $connectionEntity): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $connectionEntity->get('smtpOauthTokenUrl'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: " . $error);
        }

        $result = json_decode($response, true);

        if (empty($result['access_token'])) {
            throw new BadRequest("Error when trying to get access token");
        }

        $result['expires_at'] = time() + ((int)$result['expires_in']) - 10;

        $connectionEntity->set('smtpAccessData', $this->encryptPassword(json_encode($result)));
        $this->getEntityManager()->saveEntity($connectionEntity);

        return $result;
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

    public function getConfig(): Config
    {
        return $this->container->get('config');
    }

    public function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}

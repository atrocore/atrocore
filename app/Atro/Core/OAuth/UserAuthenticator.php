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

namespace Atro\Core\OAuth;

use Atro\Core\Container;
use Atro\Core\Exceptions\Forbidden;
use Atro\Entities\User;
use Espo\Core\Utils\Auth;
use Espo\Core\Utils\Authentication\AbstractAuthentication;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies the credentials of a user by the authentication types registered in the metadata ("app.authentication.types"),
 * e.g. a local password or LDAP. Every attempt is logged (AuthLogRecord); too many failed attempts from an IP address
 * block it for a while.
 */
class UserAuthenticator
{
    public const FAILED_ATTEMPTS_PERIOD = '60 seconds';
    public const MAX_FAILED_ATTEMPT_NUMBER = 10;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @throws Forbidden When the IP address has made too many failed attempts.
     */
    public function authenticate(string $username, string $password, ServerRequestInterface $request): ?User
    {
        $ipAddress = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');

        $this->checkFailedAttemptsLimit($ipAddress);

        $user = $this->findUser($username, $password, $request);

        $denialReason = null;
        if ($user === null) {
            $denialReason = 'CREDENTIALS';
        } elseif (!$user->isActive()) {
            $denialReason = 'INACTIVE_USER';
        }

        $this->log($username, $user, $denialReason, $ipAddress, $request);

        return $denialReason === null ? $user : null;
    }

    private function findUser(string $username, string $password, ServerRequestInterface $request): ?User
    {
        $auth = new Auth($this->container, false, $request);

        foreach (array_reverse($this->container->get('metadata')->get('app.authentication.types', [])) as $className) {
            if (!is_a($className, AbstractAuthentication::class, true)) {
                continue;
            }

            $user = (new $className($auth, $this->container))->login($username, $password);
            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    private function checkFailedAttemptsLimit(string $ipAddress): void
    {
        $config = $this->container->get('config');

        $period = $config->get('authFailedAttemptsPeriod', self::FAILED_ATTEMPTS_PERIOD);
        $maxFailedAttempts = $config->get('authMaxFailedAttemptNumber', self::MAX_FAILED_ATTEMPT_NUMBER);

        $count = $this->container->get('entityManager')->getRepository('AuthLogRecord')
            ->where([
                'requestTime>' => (new \DateTime())->modify('-' . $period)->format('U'),
                'ipAddress'    => $ipAddress,
                'isDenied'     => true,
            ])
            ->count();

        if ($count > $maxFailedAttempts) {
            $GLOBALS['log']->warning("AUTH: Max failed login attempts exceeded for IP '$ipAddress'.");
            throw new Forbidden('Max failed login attempts exceeded.');
        }
    }

    private function log(string $username, ?User $user, ?string $denialReason, string $ipAddress, ServerRequestInterface $request): void
    {
        $entityManager = $this->container->get('entityManager');

        $record = $entityManager->getEntity('AuthLogRecord');
        $record->set([
            'username'      => $username,
            'userId'        => $user?->id,
            'ipAddress'     => $ipAddress,
            'requestTime'   => $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? microtime(true),
            'requestMethod' => $request->getMethod(),
            'requestUrl'    => (string)$request->getUri(),
            'isDenied'      => $denialReason !== null,
            'denialReason'  => $denialReason,
        ]);

        $entityManager->saveEntity($record);
    }
}

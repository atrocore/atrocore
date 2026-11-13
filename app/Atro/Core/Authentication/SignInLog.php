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

namespace Atro\Core\Authentication;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\Config;
use Atro\Entities\User;
use Espo\ORM\EntityManager;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The log of the sign-in attempts (AuthLogRecord). Too many failed attempts from an IP address block it for a while.
 */
class SignInLog
{
    public const FAILED_ATTEMPTS_PERIOD = '60 seconds';
    public const MAX_FAILED_ATTEMPT_NUMBER = 10;

    public function __construct(private readonly EntityManager $entityManager, private readonly Config $config)
    {
    }

    /**
     * @throws Forbidden When the IP address has made too many failed attempts.
     */
    public function checkFailedAttemptsLimit(string $ipAddress): void
    {
        $period = $this->config->get('authFailedAttemptsPeriod', self::FAILED_ATTEMPTS_PERIOD);
        $maxFailedAttempts = $this->config->get('authMaxFailedAttemptNumber', self::MAX_FAILED_ATTEMPT_NUMBER);

        $count = $this->entityManager->getRepository('AuthLogRecord')
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

    /**
     * @param string|null $denialReason Null for a successful sign-in, e.g. "CREDENTIALS" or "INACTIVE_USER" otherwise.
     */
    public function log(string $username, ?User $user, ?string $denialReason, ServerRequestInterface $request): void
    {
        $record = $this->entityManager->getEntity('AuthLogRecord');
        $record->set([
            'username'      => $username,
            'userId'        => $user?->id,
            'ipAddress'     => (string)($request->getServerParams()['REMOTE_ADDR'] ?? ''),
            'requestTime'   => $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? microtime(true),
            'requestMethod' => $request->getMethod(),
            'requestUrl'    => (string)$request->getUri(),
            'isDenied'      => $denialReason !== null,
            'denialReason'  => $denialReason,
        ]);

        $this->entityManager->saveEntity($record);
    }
}

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

use Atro\Core\Utils\Config;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;

/**
 * The requests to change the password by the link sent to the email of the user. Only the hash of the token is stored:
 * the token itself is known only to the owner of the email.
 */
class PasswordChangeRequests
{
    public const TABLE = 'password_change_request';
    public const DEFAULT_RESEND_INTERVAL = 1;
    public const DEFAULT_LIFETIME = 15;

    public function __construct(private readonly Connection $connection, private readonly Config $config)
    {
    }

    /**
     * Replaces the previous requests of the user with a new one and returns its token.
     */
    public function create(string $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $now = new \DateTime();

        $this->removeByUser($userId);

        $this->connection->createQueryBuilder()
            ->insert(self::TABLE)
            ->values([
                'id'         => ':id',
                'token_hash' => ':tokenHash',
                'user_id'    => ':userId',
                'expires_at' => ':expiresAt',
                'created_at' => ':createdAt',
            ])
            ->setParameter('id', IdGenerator::uuid())
            ->setParameter('tokenHash', $this->hash($token))
            ->setParameter('userId', $userId)
            ->setParameter('expiresAt', (clone $now)->modify('+' . $this->getLifetime() . ' minutes')->format('Y-m-d H:i:s'))
            ->setParameter('createdAt', $now->format('Y-m-d H:i:s'))
            ->executeStatement();

        return $token;
    }

    /**
     * The user of the request that has not expired yet.
     */
    public function findUserId(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        $userId = $this->connection->createQueryBuilder()
            ->select('user_id')
            ->from(self::TABLE)
            ->where('token_hash = :tokenHash')
            ->andWhere('expires_at > :now')
            ->setParameter('tokenHash', $this->hash($token))
            ->setParameter('now', date('Y-m-d H:i:s'))
            ->fetchOne();

        return $userId === false ? null : (string)$userId;
    }

    /**
     * A new request is not sent until the resend interval has passed since the previous one.
     */
    public function isResendAllowed(string $userId): bool
    {
        $createdAfter = (new \DateTime())->modify('-' . $this->getResendInterval() . ' minutes');

        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::TABLE)
            ->where('user_id = :userId')
            ->andWhere('created_at > :createdAfter')
            ->setParameter('userId', $userId)
            ->setParameter('createdAfter', $createdAfter->format('Y-m-d H:i:s'))
            ->fetchOne();

        return $id === false;
    }

    public function removeByUser(string $userId): void
    {
        $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->executeStatement();
    }

    /**
     * An expired request is kept while it is needed to check the resend interval.
     */
    public function deleteExpired(): void
    {
        $createdBefore = (new \DateTime())->modify('-' . $this->getResendInterval() . ' minutes');

        $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('expires_at < :now')
            ->andWhere('created_at < :createdBefore')
            ->setParameter('now', date('Y-m-d H:i:s'))
            ->setParameter('createdBefore', $createdBefore->format('Y-m-d H:i:s'))
            ->executeStatement();
    }

    public function getResendInterval(): int
    {
        return max(1, (int)$this->config->get('passwordChangeRequestResendInterval', self::DEFAULT_RESEND_INTERVAL));
    }

    protected function getLifetime(): int
    {
        return max(1, (int)$this->config->get('passwordChangeRequestLifetime', self::DEFAULT_LIFETIME));
    }

    protected function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

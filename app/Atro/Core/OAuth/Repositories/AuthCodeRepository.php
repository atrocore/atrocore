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

namespace Atro\Core\OAuth\Repositories;

use Atro\Core\OAuth\Entities\AuthCodeEntity;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public const TABLE = 'oauth_auth_code';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        try {
            $this->connection->createQueryBuilder()
                ->insert(self::TABLE)
                ->values([
                    'id'         => ':id',
                    'identifier' => ':identifier',
                    'client_id'  => ':clientId',
                    'user_id'    => ':userId',
                    'scopes'     => ':scopes',
                    'expires_at' => ':expiresAt',
                    'is_revoked' => ':isRevoked',
                    'created_at' => ':createdAt',
                ])
                ->setParameter('id', IdGenerator::uuid())
                ->setParameter('identifier', $authCodeEntity->getIdentifier())
                ->setParameter('clientId', $authCodeEntity->getClient()->getIdentifier())
                ->setParameter('userId', $authCodeEntity->getUserIdentifier())
                ->setParameter('scopes', json_encode($authCodeEntity->getScopes()))
                ->setParameter('expiresAt', $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'))
                ->setParameter('isRevoked', false, ParameterType::BOOLEAN)
                ->setParameter('createdAt', date('Y-m-d H:i:s'))
                ->executeStatement();
        } catch (UniqueConstraintViolationException $e) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        $this->connection->createQueryBuilder()
            ->update(self::TABLE)
            ->set('is_revoked', ':true')
            ->where('identifier = :identifier')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('identifier', $codeId)
            ->executeStatement();
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::TABLE)
            ->where('identifier = :identifier')
            ->andWhere('is_revoked = :false')
            ->setParameter('identifier', $codeId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        return $id === false;
    }
}

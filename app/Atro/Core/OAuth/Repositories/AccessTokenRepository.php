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

use Atro\Core\OAuth\Entities\AccessTokenEntity;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public const TABLE = 'oauth_access_token';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
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
                ->setParameter('identifier', $accessTokenEntity->getIdentifier())
                ->setParameter('clientId', $accessTokenEntity->getClient()->getIdentifier())
                ->setParameter('userId', $accessTokenEntity->getUserIdentifier())
                ->setParameter('scopes', json_encode($accessTokenEntity->getScopes()))
                ->setParameter('expiresAt', $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'))
                ->setParameter('isRevoked', false, ParameterType::BOOLEAN)
                ->setParameter('createdAt', date('Y-m-d H:i:s'))
                ->executeStatement();
        } catch (UniqueConstraintViolationException $e) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->connection->createQueryBuilder()
            ->update(self::TABLE)
            ->set('is_revoked', ':true')
            ->where('identifier = :identifier')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('identifier', $tokenId)
            ->executeStatement();
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(self::TABLE)
            ->where('identifier = :identifier')
            ->andWhere('is_revoked = :false')
            ->setParameter('identifier', $tokenId)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchOne();

        return $id === false;
    }
}

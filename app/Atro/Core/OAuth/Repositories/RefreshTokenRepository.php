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

use Atro\Core\OAuth\Entities\RefreshTokenEntity;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public const TABLE = 'oauth_refresh_token';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        try {
            $this->connection->createQueryBuilder()
                ->insert(self::TABLE)
                ->values([
                    'id'                      => ':id',
                    'identifier'              => ':identifier',
                    'access_token_id'         => ':accessTokenId',
                    'expires_at'              => ':expiresAt',
                    'is_revoked'              => ':isRevoked',
                    'created_at'              => ':createdAt',
                ])
                ->setParameter('id', IdGenerator::uuid())
                ->setParameter('identifier', $refreshTokenEntity->getIdentifier())
                ->setParameter('accessTokenId', $this->getAccessTokenId($refreshTokenEntity->getAccessToken()->getIdentifier()))
                ->setParameter('expiresAt', $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'))
                ->setParameter('isRevoked', false, ParameterType::BOOLEAN)
                ->setParameter('createdAt', date('Y-m-d H:i:s'))
                ->executeStatement();
        } catch (UniqueConstraintViolationException $e) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->connection->createQueryBuilder()
            ->update(self::TABLE)
            ->set('is_revoked', ':true')
            ->where('identifier = :identifier')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('identifier', $tokenId)
            ->executeStatement();
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
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

    private function getAccessTokenId(string $accessTokenIdentifier): ?string
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(AccessTokenRepository::TABLE)
            ->where('identifier = :identifier')
            ->setParameter('identifier', $accessTokenIdentifier)
            ->fetchOne();

        return $id === false ? null : (string)$id;
    }
}

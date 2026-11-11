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

use Atro\Core\OAuth\Repositories\AccessTokenRepository;
use Atro\Core\OAuth\Repositories\AuthCodeRepository;
use Atro\Core\OAuth\Repositories\RefreshTokenRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Revokes the issued OAuth tokens and removes the expired ones.
 */
class TokenRevoker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Revokes the access token together with its refresh tokens.
     */
    public function revokeAccessToken(string $identifier): void
    {
        $this->revokeAccessTokens('identifier', $identifier);
    }

    /**
     * Revokes the refresh token together with its access token.
     */
    public function revokeRefreshToken(string $identifier, string $accessTokenIdentifier): void
    {
        $this->revoke(RefreshTokenRepository::TABLE, 'identifier', $identifier);
        $this->revokeAccessTokens('identifier', $accessTokenIdentifier);
    }

    public function revokeUserTokens(string $userId): void
    {
        $this->revokeAccessTokens('user_id', $userId);
        $this->revoke(AuthCodeRepository::TABLE, 'user_id', $userId);
    }

    public function revokeClientTokens(string $clientId): void
    {
        $this->revokeAccessTokens('client_id', $clientId);
        $this->revoke(AuthCodeRepository::TABLE, 'client_id', $clientId);
    }

    /**
     * An expired access token is kept while a refresh token refers to it: deleting it would delete the refresh token.
     */
    public function deleteExpired(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ([RefreshTokenRepository::TABLE, AuthCodeRepository::TABLE] as $table) {
            $this->connection->createQueryBuilder()
                ->delete($table)
                ->where('expires_at < :now')
                ->setParameter('now', $now)
                ->executeStatement();
        }

        $referencedAccessTokens = $this->connection->createQueryBuilder()
            ->select('access_token_id')
            ->from(RefreshTokenRepository::TABLE)
            ->where('access_token_id IS NOT NULL');

        $this->connection->createQueryBuilder()
            ->delete(AccessTokenRepository::TABLE)
            ->where('expires_at < :now')
            ->andWhere('id NOT IN (' . $referencedAccessTokens->getSQL() . ')')
            ->setParameter('now', $now)
            ->executeStatement();
    }

    /**
     * Revokes the access tokens matching the column value together with their refresh tokens.
     */
    private function revokeAccessTokens(string $column, string $value): void
    {
        $accessTokens = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(AccessTokenRepository::TABLE)
            ->where("$column = :value");

        $this->connection->createQueryBuilder()
            ->update(RefreshTokenRepository::TABLE)
            ->set('is_revoked', ':true')
            ->where('access_token_id IN (' . $accessTokens->getSQL() . ')')
            ->setParameter('value', $value)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();

        $this->revoke(AccessTokenRepository::TABLE, $column, $value);
    }

    private function revoke(string $table, string $column, string $value): void
    {
        $this->connection->createQueryBuilder()
            ->update($table)
            ->set('is_revoked', ':true')
            ->where("$column = :value")
            ->setParameter('value', $value)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();
    }
}

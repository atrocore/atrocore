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

use Atro\Core\OAuth\Entities\ClientEntity;
use Atro\Core\Utils\PasswordHash;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * Reads the OAuth clients from the records of the OauthClient entity.
 */
class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(private readonly Connection $connection, private readonly PasswordHash $passwordHash)
    {
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $row = $this->fetchActiveClient($clientIdentifier);
        if ($row === null) {
            return null;
        }

        return new ClientEntity(
            $row['id'],
            (string)$row['name'],
            [],
            !empty($row['is_confidential']),
            $this->getGrantTypes($row),
            $row['user_id'] ?: null
        );
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $row = $this->fetchActiveClient($clientIdentifier);
        if ($row === null) {
            return false;
        }

        if ($grantType !== null && !in_array($grantType, $this->getGrantTypes($row), true)) {
            return false;
        }

        if (empty($row['is_confidential'])) {
            return true;
        }

        return $clientSecret !== null && $this->passwordHash->verify($clientSecret, $row['secret_hash']);
    }

    private function fetchActiveClient(string $clientIdentifier): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'is_confidential', 'client_credentials_grant', 'authorization_code_grant', 'refresh_token_grant', 'user_id', 'secret_hash')
            ->from('oauth_client')
            ->where('id = :id')
            ->andWhere('deleted = :false')
            ->andWhere('is_active = :true')
            ->setParameter('id', $clientIdentifier)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * The grants the client may use. The client credentials grant needs a confidential client, the refresh token grant
     * continues the sessions opened by the authorization code grant.
     */
    private function getGrantTypes(array $row): array
    {
        $grantTypes = [];

        if (!empty($row['is_confidential']) && !empty($row['client_credentials_grant'])) {
            $grantTypes[] = 'client_credentials';
        }

        if (!empty($row['authorization_code_grant'])) {
            $grantTypes[] = 'authorization_code';

            if (!empty($row['refresh_token_grant'])) {
                $grantTypes[] = 'refresh_token';
            }
        }

        return $grantTypes;
    }
}

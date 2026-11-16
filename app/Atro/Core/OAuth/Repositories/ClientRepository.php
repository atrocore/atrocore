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
use Atro\Core\OAuth\UiClient;
use Atro\Core\Utils\Config;
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
    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHash $passwordHash,
        private readonly Config $config
    ) {
    }

    /**
     * A redirect URI returns the user agent to the client: an http or https URI, or a private-use URI scheme in
     * reverse domain notation of a native application (RFC 8252, section 7.1). Other schemes, such as "javascript:"
     * or "data:", would run in the context of AtroCore. A fragment is not allowed (RFC 6749, section 3.1.2).
     */
    public static function isAllowedRedirectUri(string $redirectUri): bool
    {
        $parts = parse_url($redirectUri);
        if ($parts === false || empty($parts['scheme']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'http' || $scheme === 'https') {
            return !empty($parts['host']);
        }

        return str_contains($scheme, '.') && preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1;
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
            $this->getRedirectUris($row),
            !empty($row['is_confidential']),
            $this->getGrantTypes($row),
            $row['user_id'] ?: null,
            !empty($row['is_consent_required'])
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

    /**
     * The origins of the redirect URIs of the active public clients with the authorization code grant: the browser-based
     * applications that call the API from another origin (CORS).
     */
    public function getPublicClientOrigins(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('redirect_uris')
            ->from('oauth_client')
            ->where('deleted = :false')
            ->andWhere('is_active = :true')
            ->andWhere('is_confidential = :false')
            ->andWhere('authorization_code_grant = :true')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        $origins = [];
        foreach ($rows as $redirectUris) {
            foreach ($this->decodeList($redirectUris) as $redirectUri) {
                $parts = parse_url((string)$redirectUri);
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                    $origins[] = strtolower($parts['scheme'] . '://' . $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
                }
            }
        }

        return array_values(array_unique($origins));
    }

    private function fetchActiveClient(string $clientIdentifier): ?array
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'name', 'is_confidential', 'client_credentials_grant', 'authorization_code_grant', 'refresh_token_grant', 'redirect_uris', 'is_consent_required', 'user_id', 'secret_hash')
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

    /**
     * The redirect URI of the client of the user interface follows the site URL.
     */
    private function getRedirectUris(array $row): array
    {
        if ($row['id'] === $this->config->get(UiClient::CONFIG_KEY)) {
            return [rtrim($this->config->getSiteUrl(), '/') . UiClient::REDIRECT_PATH];
        }

        // a redirect URI saved before the rules applied is not used
        return array_values(array_filter($this->decodeList($row['redirect_uris']), [self::class, 'isAllowedRedirectUri']));
    }

    private function decodeList(?string $value): array
    {
        $list = json_decode((string)$value, true);

        return is_array($list) ? array_values($list) : [];
    }
}

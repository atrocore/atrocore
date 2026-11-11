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

namespace Atro\Core\OAuth\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

/**
 * An OAuth client: a record of the OauthClient entity as the OAuth server sees it.
 */
class ClientEntity implements ClientEntityInterface
{
    use EntityTrait;
    use ClientTrait;

    private array $grantTypes;

    private ?string $userId;

    public function __construct(string $identifier, string $name, array $redirectUris, bool $isConfidential, array $grantTypes, ?string $userId)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUris;
        $this->isConfidential = $isConfidential;
        $this->grantTypes = $grantTypes;
        $this->userId = $userId;
    }

    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }

    /**
     * The user the client acts as when it authenticates with its own credentials (client credentials grant).
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }
}

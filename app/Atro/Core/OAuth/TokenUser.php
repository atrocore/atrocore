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
use Atro\Core\OAuth\Entities\ClientEntity;
use Atro\Core\OAuth\Repositories\ClientRepository;
use Atro\Entities\User;

/**
 * The user a validated access token acts for.
 */
class TokenUser
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The user who approved the token, or the user a client acts as when the token is issued to the client itself
     * (client credentials grant, the subject of the token is the client). Null when the user is not available.
     */
    public function resolve(string $userId, string $clientId): ?User
    {
        if ($userId === $clientId) {
            $client = $this->container->get(ClientRepository::class)->getClientEntity($clientId);
            $userId = $client instanceof ClientEntity ? (string)$client->getUserId() : '';
        }

        $user = $userId !== '' ? $this->container->get('entityManager')->getEntity('User', $userId) : null;

        return $user instanceof User && $user->isActive() ? $user : null;
    }
}

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

use Atro\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * The OAuth client of the user interface: an ordinary public client with PKCE, the config refers to it. The user
 * interface signs in by it, so it is created on the installation and can be restored from the console.
 */
class UiClient
{
    public const CONFIG_KEY = 'oauthUiClientId';
    public const NAME = 'AtroCore UI';
    public const CALLBACK_PATH = '/oauth/callback';

    public function __construct(private readonly EntityManager $entityManager, private readonly Config $config)
    {
    }

    public function getId(): ?string
    {
        return $this->config->get(self::CONFIG_KEY) ?: null;
    }

    public function getRedirectUri(): string
    {
        return rtrim($this->config->getSiteUrl(), '/') . self::CALLBACK_PATH;
    }

    /**
     * Creates the client when it does not exist, otherwise turns it on again with the settings the sign-in needs.
     */
    public function restore(): Entity
    {
        $client = $this->getId() !== null ? $this->entityManager->getEntity('OauthClient', $this->getId()) : null;
        if (empty($client)) {
            $client = $this->entityManager->getRepository('OauthClient')->get();
            $client->set('name', self::NAME);
        }

        $client->set([
            'isActive'               => true,
            'isConfidential'         => false,
            'clientCredentialsGrant' => false,
            'authorizationCodeGrant' => true,
            'refreshTokenGrant'      => true,
            'isConsentRequired'      => false,
            'redirectUris'           => [$this->getRedirectUri()],
        ]);
        $this->entityManager->saveEntity($client);

        if ($this->getId() !== $client->id) {
            $this->config->set(self::CONFIG_KEY, $client->id);
            $this->config->save();
        }

        return $client;
    }
}

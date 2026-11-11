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
use League\OAuth2\Server\CryptKey;

/**
 * The keys of the OAuth server: the RSA key pair that signs the access tokens and the key that encrypts
 * the refresh tokens and the authorization codes.
 */
class Keys
{
    public const DIR = 'data/keys';
    public const PRIVATE_KEY_PATH = 'data/keys/oauth-private.key';
    public const PUBLIC_KEY_PATH = 'data/keys/oauth-public.key';
    public const ENCRYPTION_KEY_CONFIG_KEY = 'oauthEncryptionKey';

    public function __construct(private readonly Config $config)
    {
    }

    public static function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function getPrivateKey(): CryptKey
    {
        return new CryptKey($this->getAbsolutePath(self::PRIVATE_KEY_PATH));
    }

    public function getPublicKey(): CryptKey
    {
        return new CryptKey($this->getAbsolutePath(self::PUBLIC_KEY_PATH));
    }

    public function getEncryptionKey(): string
    {
        $key = (string)$this->config->get(self::ENCRYPTION_KEY_CONFIG_KEY);
        if ($key === '') {
            throw new \Error('Option "' . self::ENCRYPTION_KEY_CONFIG_KEY . '" does not exist in config.php');
        }

        return $key;
    }

    /**
     * Creates the RSA key pair, when it does not exist yet.
     */
    public function createKeyPair(): void
    {
        if (file_exists(self::PRIVATE_KEY_PATH) && file_exists(self::PUBLIC_KEY_PATH)) {
            return;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false || !openssl_pkey_export($resource, $privateKey)) {
            throw new \Error('The OAuth key pair cannot be created: ' . openssl_error_string());
        }

        $publicKey = openssl_pkey_get_details($resource)['key'];

        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0700, true);
        }

        $this->writeKey(self::PRIVATE_KEY_PATH, $privateKey);
        $this->writeKey(self::PUBLIC_KEY_PATH, $publicKey);
    }

    /**
     * CryptKey reads the key by the "file://" stream wrapper, which needs an absolute path.
     */
    private function getAbsolutePath(string $path): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    private function writeKey(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new \Error("The OAuth key '$path' cannot be written.");
        }

        chmod($path, 0600);
    }
}

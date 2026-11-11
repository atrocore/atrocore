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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\OAuth\Keys;

/**
 * Creates the RSA key pair of the OAuth server.
 */
class CreateOauthKeys implements AfterInstallInterface
{
    public function __construct(private readonly Keys $keys)
    {
    }

    public static function getSortOrder(): int
    {
        return 90;
    }

    public function run(): void
    {
        $this->keys->createKeyPair();
    }
}

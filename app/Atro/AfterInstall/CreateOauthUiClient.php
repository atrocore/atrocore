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
use Atro\Core\OAuth\UiClient;

/**
 * Creates the OAuth client the user interface signs in by.
 */
class CreateOauthUiClient implements AfterInstallInterface
{
    public function __construct(private readonly UiClient $uiClient)
    {
    }

    public static function getSortOrder(): int
    {
        return 95;
    }

    public function run(): void
    {
        $this->uiClient->restore();
    }
}

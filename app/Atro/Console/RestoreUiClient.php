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

namespace Atro\Console;

use Atro\Core\OAuth\UiClient;

class RestoreUiClient extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Restore the OAuth client of the user interface: create it when it does not exist, turn it on and set its redirect URI by the site URL.';
    }

    public function run(array $data): void
    {
        try {
            $client = $this->getContainer()->get(UiClient::class)->restore();
        } catch (\Throwable $e) {
            self::show('The OAuth client of the user interface is not restored: ' . $e->getMessage(), self::ERROR, true);
        }

        $GLOBALS['log']->warning("OAuth client of the user interface '{$client->id}' restored from the console.");

        self::show("The OAuth client of the user interface '{$client->id}' is restored.", self::SUCCESS);
    }
}

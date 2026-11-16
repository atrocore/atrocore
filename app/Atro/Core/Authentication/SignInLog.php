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

namespace Atro\Core\Authentication;

use Atro\Entities\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Writes the sign-in attempts to the log of the application: the failed ones as warnings, e.g. for fail2ban or a SIEM,
 * the successful ones as information. Guessing is limited by the rate limit of the sign-in routes.
 */
class SignInLog
{
    /**
     * @param string|null $denialReason Null for a successful sign-in, e.g. "CREDENTIALS" or "INACTIVE_USER" otherwise.
     */
    public function log(string $username, ?User $user, ?string $denialReason, ServerRequestInterface $request): void
    {
        $ipAddress = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');

        if ($denialReason === null) {
            $GLOBALS['log']->info("AUTH: User '$username' ({$user?->id}) signed in from IP '$ipAddress'.");
            return;
        }

        $GLOBALS['log']->warning("AUTH: Sign-in of user '$username' from IP '$ipAddress' denied: $denialReason.");
    }
}

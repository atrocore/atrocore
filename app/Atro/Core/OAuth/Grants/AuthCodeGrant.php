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

namespace Atro\Core\OAuth\Grants;

use League\OAuth2\Server\Grant\AuthCodeGrant as BaseAuthCodeGrant;

/**
 * The authorization code grant, issuing the tokens with the lifetimes of the client.
 */
class AuthCodeGrant extends BaseAuthCodeGrant
{
    use ClientTokenLifetimesTrait;
}

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

namespace Atro\EntryPoints;

/**
 * The sign-in page of the OAuth authorization endpoint (/oauth/authorize): the user approves or denies the
 * authorization request, which the page reads from the query string.
 */
class OauthAuthorize extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $runScript = "
            app.getController('OauthAuthorize', function (controller) {
                controller.doAction('authorize');
            });
        ";
        $vars['classReplaceMap'] = json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap']));

        $this->getClientManager()->display($runScript, null, $vars);
    }
}

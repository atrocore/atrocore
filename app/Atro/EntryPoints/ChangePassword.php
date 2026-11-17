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

namespace Atro\EntryPoints;

use Atro\Core\Authentication\PasswordChangeRequests;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;

class ChangePassword extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $id = $_GET['id'] ?? null;
        if (empty($id) || !is_string($id)) {
            throw new BadRequest();
        }

        if ($this->container->get(PasswordChangeRequests::class)->findUserId($id) === null) {
            throw new NotFound();
        }

        $requestId = json_encode($id);

        $runScript = "
            app.getController(null, function (controller) {
                controller.entire('views/user/password-change-request', {requestId: {$requestId}}, function (view) {
                    view.render();
                });
            });
        ";
        $vars['classReplaceMap'] = json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap']));

        $this->getClientManager()->display($runScript, null, $vars);
    }
}

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\EntryPoints;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;

class ChangePassword extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $id = $_GET['id'] ?? null;
        if (empty($id)) {
            throw new BadRequest();
        }

        $p = $this->getEntityManager()->getRepository('PasswordChangeRequest')
            ->where([
                'requestId' => $id
            ])
            ->findOne();

        if (!$p) {
            throw new NotFound();
        }

        $runScript = "
            app.getController('PasswordChangeRequest', function (controller) {
                controller.doAction('passwordChange', '{$id}');
            });
        ";
        $vars['classReplaceMap'] = json_encode($this->getMetadata()->get(['app', 'clientClassReplaceMap']));

        $this->getClientManager()->display($runScript, null, $vars);
    }
}

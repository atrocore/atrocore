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

use Atro\ConnectionType\ConnectionSmtp;
use Atro\Entities\Connection;
use Atro\EntryPoints\AbstractEntryPoint;
use MicrosoftConnector\ConnectionType\ConnectionMsGraph;

class OauthSmtpCallback extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $redirectUrl = "/";

        if (!empty($_GET['state'])) {
            $connectionEntity = $this->getEntityManager()->getRepository('Connection')->get($_GET['state']);
            if (!empty($connectionEntity)) {
                $redirectUrl = '/#Connection/view/' . $connectionEntity->get('id');
                if (!empty($_GET['code'])) {
                    $this->getConnectionSmtp($connectionEntity)->createAccessTokenFromAuthCode($connectionEntity, $_GET['code']);
                }
            }
        }

        header("Location: " . $redirectUrl);
        die();
    }

    protected function getConnectionSmtp(Connection $connectionEntity): ConnectionSmtp
    {
        return $this->container->get('connectionFactory')->create($connectionEntity);
    }
}

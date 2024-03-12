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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;

class Pdf extends AbstractEntryPoint
{
    public static bool $authRequired = true;

    public function run()
    {
        if (empty($_GET['entityId']) || empty($_GET['entityType']) || empty($_GET['templateId'])) {
            throw new BadRequest();
        }

        $entityId = $_GET['entityId'];
        $entityType = $_GET['entityType'];
        $templateId = $_GET['templateId'];

        $entity = $this->getEntityManager()->getEntity($entityType, $entityId);
        $template = $this->getEntityManager()->getEntity('Template', $templateId);

        if (!$entity || !$template) {
            throw new NotFound();
        }

        $this->getServiceFactory()->create('Pdf')->buildFromTemplate($entity, $template, true);

        exit;
    }
}


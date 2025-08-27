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

namespace Atro\Services;

use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;

class IncomingWebhook extends ReferenceData
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $url = $this->getConfig()->getSiteUrl() . '?entryPoint=webhook&code=' . $entity->get('code');

        if (!empty($entity->get('hash'))) {
            $hash = trim($this->getInjection('twig')->renderTemplate($entity->get('hash'), []));
            if (!empty($hash)) {
                $url .= '&hash=' . $hash;
            }
        }

        $entity->set('url', $url);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
    }
}

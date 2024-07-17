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

namespace Atro\Services;

use Espo\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\ORM\IEntity;

class EmailTemplate extends Base
{

    public function getPreview(string $emailTemplateId, string $scope, string $entityId): array
    {
        $entity = $this->getEntityManager()->getEntity($scope, $entityId);
        $emailTemplate = $this->getEntityManager()->getEntity('EmailTemplate', $emailTemplateId);

        if (empty($emailTemplate) || empty($entity)) {
            throw new NotFound();
        }

        $userLanguage = $this->getConfig()->get('locales')[$this->getLocaleId()]['language'];

        return $this->getRepository()->getEmailData($emailTemplate, $userLanguage, ['entity' => $entity]);
    }


}

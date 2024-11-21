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

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\ReferenceData;

class EmailTemplate extends ReferenceData
{
    public function getPreview(string $emailTemplateId, string $scope, string $entityId): array
    {
        echo '<pre>';
        print_r('todo');
        die();

        $entity = $this->getEntityManager()->getEntity($scope, $entityId);
        $emailTemplate = $this->getEntityManager()->getEntity('EmailTemplate', $emailTemplateId);

        if (empty($emailTemplate) || empty($entity)) {
            throw new NotFound();
        }

        $userLanguage = $this->getConfig()->get('locales')[$this->getLocaleId()]['language'];

        return $this->getRepository()->getEmailData($emailTemplate, $userLanguage, ['entity' => $entity]);
    }


}

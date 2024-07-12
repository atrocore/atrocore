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

        return $this->getEmailData($emailTemplate, ['entity' => $entity]);
    }

    public function getEmailData(IEntity $emailTemplate, array $data = []): array
    {
        $twig = $this->getInjection('twig');
        $attachments = [];
        if (!empty($data['entity']) && $data['entity'] instanceof Entity) {
            $attachments = $this->getAttachments($data['entity']);
        }
        return [
            'emailTo'          => $emailTemplate->get('emailTo'),
            'emailCc'          => $emailTemplate->get('emailCc'),
            'subject'          => $twig->renderTemplate($emailTemplate->get('subject'), $data),
            'body'             => $twig->renderTemplate($emailTemplate->get('body'), $data),
            'attachmentsIds'   => array_column($attachments, 'id'),
            'attachmentsNames' => array_column($attachments, 'name'),
        ];
    }

    public function getAttachments(Entity $entity): array
    {
        $attachments = [];
        foreach ($entity->getFields() as $field => $defs) {
            if ($defs['type'] === 'file') {
                $file = $entity->get($field);
                if (!empty($file)) {
                    $attachments[] = ['id' => $file->get('id'), 'name' => $file->get('name')];
                }
            }
        }
        return $attachments;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
    }

}

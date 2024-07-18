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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\DataManager;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

class EmailTemplate extends Base
{
    public function getEmailData(IEntity $emailTemplate, ?string $language = null, array $data = []): array
    {
        $twig = $this->getInjection('twig');
        $attachments = [];
        if (!empty($data['entity']) && $data['entity'] instanceof \Espo\Core\ORM\Entity) {
            $attachments = $this->getAttachments($data['entity']);
        }

        $subject = $emailTemplate->get('subject');
        $body = $emailTemplate->get('body');

        if (!empty($language) && $language !== 'en_US') {
            $suffix = ucfirst(Util::toCamelCase(strtolower($language)));
            $field = 'subject' . $suffix;
            if (!empty($emailTemplate->get($field))) {
                $subject = $emailTemplate->get($field);
            }
            $field = 'body' . $suffix;
            if (!empty($emailTemplate->get($field))) {
                $body = $emailTemplate->get($field);
            }
        }

        $subject = str_replace(["\n", "\r"], '', $subject);

        return [
            'emailTo'          => $emailTemplate->get('emailTo'),
            'emailCc'          => $emailTemplate->get('emailCc'),
            'subject'          => $twig->renderTemplate($subject, $data),
            'body'             => $twig->renderTemplate($body, $data),
            'attachmentsIds'   => array_column($attachments, 'id'),
            'attachmentsNames' => array_column($attachments, 'name', 'id'),
        ];
    }

    public function getAttachments(Entity $entity): array
    {
        $attachments = [];
        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields']) ?? [] as $field => $defs) {
            if (!empty($defs['type']) && $defs['type'] === 'file') {
                $file = $entity->get($field);
                if (!empty($file)) {
                    $attachments[] = ['id' => $file->get('id'), 'name' => $file->get('name')];
                }
            }
        }
        return $attachments;
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        if (in_array($entity->get('id'), ['mention', 'notePost', 'notePostNoParent', 'ownership', 'assignment'])) {
            throw new BadRequest($this->getLanguage()->translate("notificationTemplatesCannotBeDeleted", "exceptions", $this->entityType));
        }

        parent::beforeRemove($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
    }

}

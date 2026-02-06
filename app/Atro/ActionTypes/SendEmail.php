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

namespace Atro\ActionTypes;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\EventManager\Event;
use Atro\Core\Mail\Sender;
use Atro\Core\Twig\Twig;
use Atro\Core\Utils\Note as NoteUtil;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class SendEmail extends AbstractAction
{
    public function executeEmailPreview(Entity $action, string $entityId): array
    {
        $entity = $this->getEntityManager()->getEntity($action->get('sourceEntity'), $entityId);

        return $this->getEmailData($action, $entity);
    }

    public function executeNow(Entity $action, \stdClass $input): bool
    {
        $entity = $this->getSourceEntity($action, $input);

        $emailData = $this->getEmailData($action, $entity);
        foreach (['subject', 'body', 'emailTo', 'emailCc', 'emailBcc'] as $key) {
            if (property_exists($input, $key)) {
                $emailData[$key] = $input->$key;
            }
        }

        $data = [
            'subject' => $emailData['subject'],
            'body'    => $emailData['body'],
            'to'      => implode(';', $emailData['emailTo']),
            'isHtml'  => !empty($this->getEmailTemplate($emailData['emailTemplateId'])->get('isHtml'))
        ];

        if (!empty($emailData['emailCc'])) {
            $data['cc'] = implode(';', $emailData['emailCc']);
        }

        if (!empty($emailData['emailBcc'])) {
            $data['bcc'] = implode(';', $emailData['emailBcc']);
        }

        if (!empty($data['to'])) {
            $this->container->get(Sender::class)->send($data, $action->get('connection'));
            if (!empty($entity)) {
                $this->createNote($entity, $emailData);
            }
        }

        return true;
    }

    public function getEmailData(Entity $action, ?Entity $sourceEntity): array
    {
        $templateData = [
            'entity'     => $sourceEntity,
            'collection' => null
        ];

        $targetEntity = $action->get('targetEntity');
        if (!empty($targetEntity)) {
            $where = [];
            if (!empty($this->getWhere($action))) {
                $whereJson = json_encode($this->getWhere($action));
                $whereJson = $this->container->get('twig')->renderTemplate($whereJson, $templateData);
                $where = @json_decode($whereJson, true);
            }

            $searchEntity = $action->get('searchEntity') ?? $targetEntity;

            /** @var \Espo\Core\SelectManagers\Base $selectManager */
            $selectManager = $this->container->get('selectManagerFactory')->create($searchEntity);

            /** @var \Atro\Core\Templates\Repositories\Base $repository */
            $repository = $this->getEntityManager()->getRepository($searchEntity);

            $selectParams = $selectManager->getSelectParams(['where' => $where], true, true);
            $repository->handleSelectParams($selectParams);

            $templateData['collection'] = $repository->find($selectParams);
        }

        /** @var Twig $twig */
        $twig = $this->container->get('twig');

        $emailTemplateId = $action->get('emailTemplateId');
        $emailTo = $action->get('emailTo');
        $emailCc = $action->get('emailCc') ?? [];
        $emailBcc = $action->get('emailBcc') ?? [];
        if ($action->get('mode') === 'script') {
            $script = "{% set emailTo=[] %}{% set emailCc=[] %}{% set emailBcc=[] %}{% set emailTemplateId=null %}";
            $script .= $action->get('emailScript');
            $script .= " {% set data = {'emailTemplateId': emailTemplateId, 'emailTo': emailTo, 'emailCc': emailCc, 'emailBcc': emailBcc} %} PREPARED_SCRIPT_START=`{{data|json_encode|raw}}`PREPARED_SCRIPT_END";

            $res = $twig->renderTemplate($script, $templateData);
            if (preg_match_all("/PREPARED_SCRIPT_START=`(.*)`PREPARED_SCRIPT_END$/", $res, $matches)) {
                if (isset($matches[1][0])) {
                    $scriptData = @json_decode($matches[1][0], true);
                    if (!empty($scriptData)) {
                        $emailTemplateId = $scriptData['emailTemplateId'];
                        $emailTo = $scriptData['emailTo'];
                        $emailCc = $scriptData['emailCc'];
                        $emailBcc = $scriptData['emailBcc'];
                    }
                }
            }
        }

        $emailTemplate = $this->getEmailTemplate($emailTemplateId);

        $data = [
            'subject'         => $twig->renderTemplate($emailTemplate->get('subject'), $templateData),
            'body'            => $twig->renderTemplate($emailTemplate->get('body'), $templateData),
            'emailTo'         => $emailTo,
            'emailCc'         => $emailCc,
            'emailBcc'        => $emailBcc,
            'emailTemplateId' => $emailTemplateId
        ];

        return $data;
    }

    public function createNote(Entity $entity, array $data)
    {
        $noteUtil = $this->container->get(NoteUtil::class);
        if (!$noteUtil->streamEnabled($entity->getEntityType())) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => 'EmailSent',
            'parentType' => $entity->getEntityType(),
            'parentId'   => $entity->get('id'),
            'data'       => @json_encode($data),
        ]);
        $this->getEntityManager()->saveEntity($note);
    }

    protected function getEmailTemplate(?string $emailTemplateId): Entity
    {
        if (empty($emailTemplateId)) {
            throw new BadRequest("Email Template ID is required.");
        }

        $emailTemplate = $this->getEntityManager()->getEntity('EmailTemplate', $emailTemplateId);
        if (empty($emailTemplate)) {
            throw new BadRequest("Email Template '$emailTemplateId' not found.");
        }

        return $emailTemplate;
    }
}
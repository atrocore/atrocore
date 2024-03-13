<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Repositories;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Espo\Core\DataManager;
use Espo\Core\Htmlizer\Htmlizer;
use Espo\Core\Mail\Sender;
use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\Utils\TemplateFileManager;
use Espo\ORM\Entity;

/**
 * Class Notification
 */
class Notification extends RDB
{
    /**
     * @var Htmlizer|null
     */
    protected $htmlizer = null;

    public static function refreshNotReadCount(Connection $connection): void
    {
        $data = $connection->createQueryBuilder()
            ->select('n.user_id, COUNT(n.id) as total')
            ->from('notification', 'n')
            ->where('n.read = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->groupBy('n.user_id')
            ->fetchAllAssociative();

        DataManager::pushPublicData('notReadCount', json_encode(array_column($data, 'total', 'user_id')));
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew()) {
            $entity->set('number', time() - (new \DateTime('2023-01-01'))->getTimestamp());
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        self::refreshNotReadCount($this->getConnection());

        if ($entity->isNew()) {
            $this->createNote($entity);
            $this->sendEmail($entity);
        }

        parent::afterSave($entity, $options);
    }

    protected function createNote(Entity $notification): void
    {
        if (!in_array($notification->get('type'), ['Assign', 'Own'])) {
            return;
        }

        if (!$this->getMetadata()->get(['scopes', $notification->get('relatedType'), 'stream'], false)) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');

        $note->set('type', $notification->get('type'));
        $note->set('parentId', $notification->get('relatedId'));
        $note->set('parentType', $notification->get('relatedType'));

        $note->set(
            'data', [
                'userId'   => $notification->get('userId'),
                'userName' => !empty($user = $notification->get('user')) ? $user->get('name') : '',
            ]
        );

        $this->getEntityManager()->saveEntity($note);
    }

    protected function sendEmail(Entity $notification): void
    {
        $this->emailMentionInPost($notification);
        $this->emailAssignment($notification);
        $this->emailOwnership($notification);
        $this->emailNote($notification);
    }

    protected function emailMentionInPost(Entity $notification): void
    {
        if ($notification->get('type') != 'MentionInPost') {
            return;
        }

        if (!$this->getConfig()->get('mentionEmailNotifications', false)) {
            return;
        }

        if (empty($userId = $notification->get('userId'))) {
            return;
        }

        if (empty($user = $this->getEntityManager()->getEntity('User', $userId))) {
            return;
        }

        if (empty($emailAddress = $user->get('emailAddress'))) {
            return;
        }

        if (empty($preferences = $this->getEntityManager()->getEntity('Preferences', $userId))) {
            return;
        }

        if (!$preferences->get('receiveMentionEmailNotifications')) {
            return;
        }

        if ($notification->get('relatedType') !== 'Note' || !$notification->get('relatedId')) {
            return;
        }

        if (empty($note = $this->getEntityManager()->getEntity('Note', $notification->get('relatedId')))) {
            return;
        }

        $parentId = $note->get('parentId');
        $parentType = $note->get('parentType');

        $data = [];

        if ($parentId && $parentType) {
            $parent = $this->getEntityManager()->getEntity($parentType, $parentId);
            if (!$parent) {
                return;
            }

            $data['url'] = $this->getSiteUrl($user) . '/#' . $parentType . '/view/' . $parentId;
            $data['parentName'] = $parent->get('name');
            $data['parentType'] = $parentType;
            $data['parentId'] = $parentId;
        } else {
            $data['url'] = $this->getSiteUrl($user) . '/#Notification';
        }

        $data['userName'] = $note->get('createdByName');

        $data['post'] = nl2br($note->get('post'));

        $subjectTpl = $this->getTemplateFileManager()->getTemplate('mention', 'subject');
        $subjectTpl = str_replace(["\n", "\r"], '', $subjectTpl);

        $bodyTpl = $this->getTemplateFileManager()->getTemplate('mention', 'body');

        $subject = $this->getHtmlizer()->render($note, $subjectTpl, 'mention-email-subject', $data, true);
        $body = $this->getHtmlizer()->render($note, $bodyTpl, 'mention-email-body', $data, true);

        $this->getMailSender()->sendByJob(
            [
                'subject' => $subject,
                'body'    => $body,
                'isHtml'  => true,
                'to'      => $emailAddress
            ]
        );
    }

    protected function emailAssignment(Entity $notification): void
    {
        if ($notification->get('type') != 'Assign') {
            return;
        }

        if (!$this->getConfig()->get('assignmentEmailNotifications', false)) {
            return;
        }

        if (empty($userId = $notification->get('userId'))) {
            return;
        }

        if (empty($user = $this->getEntityManager()->getEntity('User', $userId))) {
            return;
        }

        if (empty($emailAddress = $user->get('emailAddress'))) {
            return;
        }

        if (empty($preferences = $this->getEntityManager()->getEntity('Preferences', $userId))) {
            return;
        }

        if (!$preferences->get('receiveAssignmentEmailNotifications')) {
            return;
        }

        $entity = $this->getEntityManager()->getEntity($notification->get('data')['entityType'], $notification->get('data')['entityId']);
        $assignerUser = $this->getEntityManager()->getEntity('User', $notification->get('data')['changedBy']);

        $subjectTpl = $this->getTemplateFileManager()->getTemplate('assignment', 'subject', $entity->getEntityType());
        $bodyTpl = $this->getTemplateFileManager()->getTemplate('assignment', 'body', $entity->getEntityType());

        $subjectTpl = str_replace(array("\n", "\r"), '', $subjectTpl);

        $recordUrl = rtrim($this->getConfig()->get('siteUrl'), '/') . '/#' . $entity->getEntityType() . '/view/' . $entity->id;

        $data = [
            'userName'         => $user->get('name'),
            'assignerUserName' => $assignerUser->get('name'),
            'recordUrl'        => $recordUrl,
            'entityType'       => $this->getInjection('language')->translate($entity->getEntityType(), 'scopeNames')
        ];

        $data['entityTypeLowerFirst'] = lcfirst($data['entityType']);

        $subject = $this->getHtmlizer()->render($entity, $subjectTpl, 'assignment-email-subject-' . $entity->getEntityType(), $data, true);
        $body = $this->getHtmlizer()->render($entity, $bodyTpl, 'assignment-email-body-' . $entity->getEntityType(), $data, true);

        try {
            $this->getMailSender()->sendByJob(
                [
                    'subject' => $subject,
                    'body'    => $body,
                    'isHtml'  => true,
                    'to'      => $emailAddress
                ]
            );
        } catch (\Exception $e) {
            $GLOBALS['log']->error('EmailNotification: [' . $e->getCode() . '] ' . $e->getMessage());
        }
    }

    protected function emailOwnership(Entity $notification): void
    {
        if ($notification->get('type') != 'Own') {
            return;
        }

        if (!$this->getConfig()->get('assignmentEmailNotifications', false)) {
            return;
        }

        if (empty($userId = $notification->get('userId'))) {
            return;
        }

        if (empty($user = $this->getEntityManager()->getEntity('User', $userId))) {
            return;
        }

        if (empty($emailAddress = $user->get('emailAddress'))) {
            return;
        }

        if (empty($preferences = $this->getEntityManager()->getEntity('Preferences', $userId))) {
            return;
        }

        if (!$preferences->get('receiveAssignmentEmailNotifications')) {
            return;
        }

        $entity = $this->getEntityManager()->getEntity($notification->get('data')['entityType'], $notification->get('data')['entityId']);
        $assignerUser = $this->getEntityManager()->getEntity('User', $notification->get('data')['changedBy']);

        $subjectTpl = $this->getTemplateFileManager()->getTemplate('ownership', 'subject', $entity->getEntityType());
        $bodyTpl = $this->getTemplateFileManager()->getTemplate('ownership', 'body', $entity->getEntityType());

        $subjectTpl = str_replace(array("\n", "\r"), '', $subjectTpl);

        $recordUrl = rtrim($this->getConfig()->get('siteUrl'), '/') . '/#' . $entity->getEntityType() . '/view/' . $entity->id;

        $data = [
            'userName'         => $user->get('name'),
            'assignerUserName' => $assignerUser->get('name'),
            'recordUrl'        => $recordUrl,
            'entityType'       => $this->getInjection('language')->translate($entity->getEntityType(), 'scopeNames')
        ];

        $data['entityTypeLowerFirst'] = lcfirst($data['entityType']);

        $subject = $this->getHtmlizer()->render($entity, $subjectTpl, 'ownership-email-subject-' . $entity->getEntityType(), $data, true);
        $body = $this->getHtmlizer()->render($entity, $bodyTpl, 'ownership-email-body-' . $entity->getEntityType(), $data, true);

        try {
            $this->getMailSender()->sendByJob(
                [
                    'subject' => $subject,
                    'body'    => $body,
                    'isHtml'  => true,
                    'to'      => $emailAddress
                ]
            );
        } catch (\Exception $e) {
            $GLOBALS['log']->error('EmailNotification: [' . $e->getCode() . '] ' . $e->getMessage());
        }
    }

    protected function emailNote(Entity $notification): void
    {
        if ($notification->get('type') !== 'Note') {
            return;
        }

        if ($notification->get('relatedType') !== 'Note') {
            return;
        }

        if (!$notification->get('relatedId')) {
            return;
        }

        if (empty($note = $this->getEntityManager()->getEntity('Note', $notification->get('relatedId')))) {
            return;
        }

        if (empty($userId = $notification->get('userId'))) {
            return;
        }

        if (empty($user = $this->getEntityManager()->getEntity('User', $userId))) {
            return;
        }

        if (empty($this->getConfig()->get('streamEmailNotifications'))) {
            return;
        }

        if (empty($emailAddress = $user->get('emailAddress'))) {
            return;
        }

        if (empty($preferences = $this->getEntityManager()->getEntity('Preferences', $userId))) {
            return;
        }

        if (empty($preferences->get('receiveStreamEmailNotifications'))) {
            return;
        }


        $parentId = $note->get('parentId');
        $parentType = $note->get('parentType');

        $data = [];

        $data['userName'] = $note->get('createdByName');
        $data['post'] = nl2br($note->get('post'));

        if ($parentId && $parentType) {
            if (empty($parent = $this->getEntityManager()->getEntity($parentType, $parentId))) {
                return;
            }

            $data['url'] = $this->getSiteUrl($user) . '/#' . $parentType . '/view/' . $parentId;
            $data['parentName'] = $parent->get('name');
            $data['parentType'] = $parentType;
            $data['parentId'] = $parentId;

            $data['name'] = $data['parentName'];

            $data['entityType'] = $this->getInjection('language')->translate($data['parentType'], 'scopeNames');
            $data['entityTypeLowerFirst'] = lcfirst($data['entityType']);

            $subjectTpl = $this->getTemplateFileManager()->getTemplate('notePost', 'subject', $parentType);
            $bodyTpl = $this->getTemplateFileManager()->getTemplate('notePost', 'body', $parentType);

            $subjectTpl = str_replace(array("\n", "\r"), '', $subjectTpl);

            $subject = $this->getHtmlizer()->render($note, $subjectTpl, 'note-post-email-subject-' . $parentType, $data, true);
            $body = $this->getHtmlizer()->render($note, $bodyTpl, 'note-post-email-body-' . $parentType, $data, true);
        } else {
            $data['url'] = $this->getSiteUrl($user) . '/#Notification';

            $subjectTpl = $this->getTemplateFileManager()->getTemplate('notePostNoParent', 'subject');
            $bodyTpl = $this->getTemplateFileManager()->getTemplate('notePostNoParent', 'body');

            $subjectTpl = str_replace(array("\n", "\r"), '', $subjectTpl);

            $subject = $this->getHtmlizer()->render($note, $subjectTpl, 'note-post-email-subject', $data, true);
            $body = $this->getHtmlizer()->render($note, $bodyTpl, 'note-post-email-body', $data, true);
        }

        try {
            $this->getMailSender()->sendByJob(
                [
                    'subject' => $subject,
                    'body'    => $body,
                    'isHtml'  => true,
                    'to'      => $emailAddress]
            );
        } catch (\Exception $e) {
            $GLOBALS['log']->error('EmailNotification: [' . $e->getCode() . '] ' . $e->getMessage());
        }
    }

    protected function getSiteUrl(\Espo\Entities\User $user): string
    {
        return $this->getConfig()->getSiteUrl();
    }

    protected function getTemplateFileManager(): TemplateFileManager
    {
        return $this->getInjection('templateFileManager');
    }

    protected function getHtmlizer(): Htmlizer
    {
        if (is_null($this->htmlizer)) {
            $this->htmlizer = new Htmlizer($this->getInjection('fileManager'), $this->getInjection('dateTime'), $this->getInjection('number'), null);
        }

        return $this->htmlizer;
    }

    protected function getMailSender(): Sender
    {
        return $this->getInjection('mailSender');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('templateFileManager');
        $this->addDependency('fileManager');
        $this->addDependency('dateTime');
        $this->addDependency('number');
        $this->addDependency('mailSender');
        $this->addDependency('language');
    }
}


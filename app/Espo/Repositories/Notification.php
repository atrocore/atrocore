<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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
    const UPDATE_COUNT_PATH = 'data/notifications-count';

    const NOT_READ_COUNT_FILE = 'data/notReadCount.json';

    /**
     * @var Htmlizer|null
     */
    protected $htmlizer = null;

    /**
     * @var array
     */
    protected $userIdPortalCacheMap = [];

    public static function refreshNotReadCount(): void
    {
        if (!file_exists(self::UPDATE_COUNT_PATH)) {
            mkdir(self::UPDATE_COUNT_PATH, 0777, true);
            sleep(1);
        }
        file_put_contents(self::UPDATE_COUNT_PATH . '/' . time() . '.txt', '1');
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function afterSave(Entity $entity, array $options = [])
    {
        self::refreshNotReadCount();

        if ($entity->isNew()) {
            $this->sendEmail($entity);
        }

        parent::afterSave($entity, $options);
    }

    protected function sendEmail(Entity $notification): void
    {
        $this->emailMentionInPost($notification);

//        processNotificationMentionInPost
//        processNotificationNote
//        processNotificationNotePost
//        processNotificationNoteStatus
//        processNotificationNoteEmailReceived

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

        try {
            $this->getMailSender()->send(
                [
                    'subject' => $subject,
                    'body'    => $body,
                    'isHtml'  => true,
                    'to'      => $emailAddress
                ]
            );
        } catch (\Exception $e) {
            $GLOBALS['log']->error('emailMentionInPost: [' . $e->getCode() . '] ' . $e->getMessage());
        }
    }

    protected function getSiteUrl(\Espo\Entities\User $user): string
    {
        if ($user->get('isPortalUser')) {
            if (!array_key_exists($user->id, $this->userIdPortalCacheMap)) {
                $this->userIdPortalCacheMap[$user->id] = null;

                $portalIdList = $user->getLinkMultipleIdList('portals');
                $defaultPortalId = $this->getConfig()->get('defaultPortalId');

                $portalId = null;

                if (in_array($defaultPortalId, $portalIdList)) {
                    $portalId = $defaultPortalId;
                } else {
                    if (count($portalIdList)) {
                        $portalId = $portalIdList[0];
                    }
                }

                if ($portalId) {
                    $portal = $this->getEntityManager()->getEntity('Portal', $portalId);
                    $this->getEntityManager()->getRepository('Portal')->loadUrlField($portal);
                    $this->userIdPortalCacheMap[$user->id] = $portal;
                }
            } else {
                $portal = $this->userIdPortalCacheMap[$user->id];
            }

            if ($portal) {
                return rtrim($portal->get('url'), '/');
            }
        }

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
    }
}


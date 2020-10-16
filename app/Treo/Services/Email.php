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

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;

/**
 * Email service
 */
class Email extends \Espo\Services\Email
{
    /**
     * Send test email
     *
     * @param array $data
     *
     * @return bool
     */
    public function sendTestEmail($data)
    {
        $this->sendEmail($data);

        return true;
    }

    /**
     * Get subject for test email
     *
     * @return string
     */
    protected function getTestEmailSubjectTranslate(): string
    {
        return $this
            ->getEntityManager()
            ->getContainer()
            ->get('language')
            ->translate('testEmailSubject', 'messages', 'Email');
    }

    /**
     * Get entity for test email
     *
     * @param string $emailAddress
     *
     * @return Entity
     */
    protected function getTestEmailEntity(string $emailAddress): Entity
    {
        $email = $this->getEntityManager()->getEntity('Email');

        $email->set(
            [
                'subject' => $this->getTestEmailSubjectTranslate(),
                'isHtml'  => false,
                'to'      => $emailAddress
            ]
        );

        return $email;
    }

    /**
     * Send email
     *
     * @param array $data
     */
    protected function sendEmail(array $data): void
    {
        $emailSender = $this->getEntityManager()->getContainer()->get('mailSender');
        $emailSender->useSmtp($data)->send($this->getTestEmailEntity($data['emailAddress']));
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // call parent
        parent::init();

        $this->addDependency('mailSender');
    }

    /**
     * @inheritdoc
     */
    protected function getMailSender()
    {
        return $this->getInjection('mailSender');
    }

    /**
     * @inheritdoc
     */
    protected function getPreferences()
    {
        return $this->getInjection('preferences');
    }

    /**
     * @inheritdoc
     */
    protected function getCrypt()
    {
        return $this->getInjection('crypt');
    }

    /**
     * @inheritdoc
     */
    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }
}

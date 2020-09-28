<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;

/**
 * Email service
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
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

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

declare(strict_types=1);

namespace Espo\Core\Mail;

use Atro\Core\Exceptions\Error;
use Atro\Core\QueueManager;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Sender
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var QueueManager
     */
    private $queueManager;


    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var Transport
     */
    private $transport;

    /**
     * Sender constructor.
     */
    public function __construct(Config $config, QueueManager $queueManager, EntityManager $entityManager)
    {
        $this->config = $config;
        $this->queueManager = $queueManager;
        $this->entityManager = $entityManager;

        $factory = new EsmtpTransportFactory;

        $scheme = in_array($this->config->get('smtpSecurity'), ['SSL', 'TLS']) ? ($this->config->get('smtpPort') === 465 ? 'smtps' : 'smtp') : '';
        $this->transport = $factory->create(new Dsn(
            $scheme,
            $this->config->get('smtpServer') ?? '',
            $this->config->get('smtpUsername') ?? null,
            $this->config->get('smtpPassword') ?? null,
            $this->config->get('smtpPort') ?? null,
        ));

    }

    public function sendByJob(array $emailData, array $params = []): void
    {
        $this->queueManager->push('Send email', 'QueueManagerEmailSender', ['emailData' => $emailData, 'params' => $params]);
    }

    /**
     * @param array $emailData
     * @param array $params
     *
     * @throws Error
     */
    public function send(array $emailData, array $params = []): void
    {
        if($this->config->get('disableEmailDelivery') === true){
            $GLOBALS['log']->alert('Outbound emails: Email delivery is deactivated.');
            return;
        }

        if (empty($emailData['subject']) || empty($emailData['to'])) {
            throw new Error('Subject and emailTo is required.');
        }

        $fromEmail = $this->config->get('outboundEmailFromAddress');
        if (!empty($emailData['from'])) {
            $fromEmail = $emailData['from'];
        }

        if (empty($fromEmail)) {
            throw new Error('From Email is not specified.');
        }

        $email = (new Email())->from($fromEmail);

        $fromName = $this->config->get('outboundEmailFromName', $fromEmail);
        if (array_key_exists('fromName', $emailData)) {
            $fromName = $emailData['fromName'];
        }
        $email->from(new Address($fromEmail, $fromName));

        if (!empty($params['replyToAddress'])) {
            $replyToName = null;
            if (!empty($params['replyToName'])) {
                $replyToName = $params['replyToName'];
            }
            $email->replyTo(new Address($params['replyToAddress'], $replyToName));
        }

        if (!empty($emailData['to'])) {
            foreach (explode(';', $emailData['to']) as $address) {
                $email->addTo(trim($address));
            }
        }

        if (!empty($emailData['cc'])) {
            foreach (explode(';', $emailData['cc']) as $address) {
                $email->addCc(trim($address));
            }
        }

        if (!empty($emailData['bcc'])) {
            foreach (explode(';', $emailData['bcc']) as $address) {
                $email->addBcc(trim($address));
            }
        }

        if (!empty($emailData['replyTo'])) {
            foreach (explode(';', $emailData['replyTo']) as $address) {
                $email->addReplyTo(trim($address));
            }
        }

        $email->subject($emailData['subject']);

        $bodyPlain = !empty($emailData['body']) ? $emailData['body'] : '';

        if (!empty($emailData['isHtml'])) {
           $email->html($bodyPlain);
        } else {
           $email->text($this->prepareBodyPlain($bodyPlain));
        }

        if (!empty($emailData['attachments'])) {
            $attachments = $this->entityManager->getRepository('File')->where(['id' => $emailData['attachments']])->find();
            foreach ($attachments as $attachment) {
              $email->attachFromPath($attachment->getFilePath(), $attachment->get('name'), $attachment->get('mimeType'));
            }
        }

        try {
            $this->transport->send($email, Envelope::create($email));
        } catch (\Exception $e) {
            throw new Error($e->getMessage(), 500);
        }
    }

    protected function prepareBodyPlain(string $body): string
    {
        $breaks = ["<br />", "<br>", "<br/>", "<br />", "&lt;br /&gt;", "&lt;br/&gt;", "&lt;br&gt;"];
        $body = str_ireplace($breaks, "\r\n", $body);
        $body = strip_tags($body);

        $reList = [
            '/&(quot|#34);/i',
            '/&(amp|#38);/i',
            '/&(lt|#60);/i',
            '/&(gt|#62);/i',
            '/&(nbsp|#160);/i',
            '/&(iexcl|#161);/i',
            '/&(cent|#162);/i',
            '/&(pound|#163);/i',
            '/&(copy|#169);/i',
            '/&(reg|#174);/i'
        ];
        $replaceList = [
            '',
            '&',
            '<',
            '>',
            ' ',
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174)
        ];

        $body = preg_replace($reList, $replaceList, $body);

        return $body;
    }
}

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

namespace Espo\Core\Mail;

use Espo\Core\Utils\Config;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Mime\Mime as Mime;

use Zend\Mail\Message;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;

use Espo\Core\Exceptions\Error;

/**
 * Class Sender
 */
class Sender
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var SmtpTransport
     */
    protected $transport;

    /**
     * Sender constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->transport = new SmtpTransport();

        $opts = [
            'name'              => $this->config->get('smtpLocalHostName', gethostname()),
            'host'              => $this->config->get('smtpServer'),
            'port'              => $this->config->get('smtpPort'),
            'connection_config' => []
        ];

        if ($this->config->get('smtpAuth')) {
            $opts['connection_class'] = $this->config->get('smtpAuthMechanism', 'login');
            $opts['connection_config']['username'] = $this->config->get('smtpUsername');
            $opts['connection_config']['password'] = $this->config->get('smtpPassword');
        }

        if ($this->config->get('smtpSecurity')) {
            $opts['connection_config']['ssl'] = strtolower($this->config->get('smtpSecurity'));
        }

        $this->transport->setOptions(new SmtpOptions($opts));
    }

    /**
     * @param array $emailData
     * @param array $params
     *
     * @throws Error
     */
    public function send(array $emailData, array $params = []): void
    {
        if (empty($emailData['subject']) || empty($emailData['to'])) {
            throw new Error('Subject and emailTo is required.');
        }

        $fromEmail = $this->config->get('outboundEmailFromAddress');
        if (empty($this->config->get('outboundEmailFromAddress'))) {
            throw new Error('outboundEmailFromAddress is not specified in config.');
        }

        $sender = new \Zend\Mail\Header\Sender();
        $sender->setAddress($fromEmail);

        $message = new Message();
        $message->addFrom(trim($fromEmail), $this->config->get('outboundEmailFromName', $fromEmail));
        $message->getHeaders()->addHeader($sender);

        if (!empty($params['replyToAddress'])) {
            $replyToName = null;
            if (!empty($params['replyToName'])) {
                $replyToName = $params['replyToName'];
            }
            $message->setReplyTo($params['replyToAddress'], $replyToName);
        }

        if (!empty($emailData['to'])) {
            foreach (explode(';', $emailData['to']) as $address) {
                $message->addTo(trim($address));
            }
        }

        if (!empty($emailData['cc'])) {
            foreach (explode(';', $emailData['cc']) as $address) {
                $message->addCC(trim($address));
            }
        }

        if (!empty($emailData['bcc'])) {
            foreach (explode(';', $emailData['bcc']) as $address) {
                $message->addBCC(trim($address));
            }
        }

        if (!empty($emailData['replyTo'])) {
            foreach (explode(';', $emailData['replyTo']) as $address) {
                $message->addReplyTo(trim($address));
            }
        }

        $message->setSubject($emailData['subject']);

        $bodyPlain = !empty($emailData['body']) ? $emailData['body'] : '';

        if (!empty($emailData['isHtml'])) {
            $body = new MimeMessage();
            $html = new MimePart($bodyPlain);
            $html->encoding = Mime::ENCODING_QUOTEDPRINTABLE;
            $html->type = 'text/html';
            $html->charset = 'utf-8';
            $body->addPart($html);
            $messageType = 'multipart/alternative';
        } else {
            $body = $this->prepareBodyPlain($bodyPlain);
            $messageType = 'text/plain';
        }

        $message->setBody($body);

        if ($messageType == 'text/plain') {
            if ($message->getHeaders()->has('content-type')) {
                $message->getHeaders()->removeHeader('content-type');
            }
            $message->getHeaders()->addHeaderLine('Content-Type', 'text/plain; charset=UTF-8');
        } else {
            if (!$message->getHeaders()->has('content-type')) {
                $contentTypeHeader = new \Zend\Mail\Header\ContentType();
                $message->getHeaders()->addHeader($contentTypeHeader);
            }
            $message->getHeaders()->get('content-type')->setType($messageType);
        }

        $message->setEncoding('UTF-8');

        try {
            $this->transport->send($message);
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


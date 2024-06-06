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

namespace Atro\Core\Mail;

use Atro\Core\Container;
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
    private Config $config;

    private QueueManager $queueManager;

    private EntityManager $entityManager;

    private Transport $transport;

    /**
     * Sender constructor.
     */
    public function __construct(Container $container)
    {
        $this->config = $container->get('config');
        $this->queueManager = $container->get('queueManager');
        $this->entityManager = $container->get('entityManager');

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

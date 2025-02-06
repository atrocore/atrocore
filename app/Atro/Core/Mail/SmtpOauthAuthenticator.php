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

use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpOauthAuthenticator implements AuthenticatorInterface
{
    private $tokenCallback;

    public function __construct(callable $tokenCallback)
    {
        $this->tokenCallback = $tokenCallback;
    }

    public function getAuthKeyword(): string
    {
        return 'XOAUTH2';
    }

    public function getToken() : string
    {
        return ($this->tokenCallback)();
    }

    public function authenticate(EsmtpTransport $client): void
    {
        $client->executeCommand('AUTH XOAUTH2 '.base64_encode('user='.$client->getUsername()."\1auth=Bearer ".$this->getToken()."\1\1")."\r\n", [235]);
    }
}


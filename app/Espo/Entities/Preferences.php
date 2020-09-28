<?php

namespace Espo\Entities;

class Preferences extends \Espo\Core\ORM\Entity
{
    public function getSmtpParams()
    {
        $smtpParams = array();
        $smtpParams['server'] = $this->get('smtpServer');
        if ($smtpParams['server']) {
            $smtpParams['port'] = $this->get('smtpPort');
            $smtpParams['server'] = $this->get('smtpServer');
            $smtpParams['auth'] = $this->get('smtpAuth');
            $smtpParams['security'] = $this->get('smtpSecurity');
            $smtpParams['username'] = $this->get('smtpUsername');
            $smtpParams['password'] = $this->get('smtpPassword');
            return $smtpParams;
        } else {
            return false;
        }
    }
}

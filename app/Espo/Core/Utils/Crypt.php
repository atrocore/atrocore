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

namespace Espo\Core\Utils;

use Espo\Core\Injectable;

class Crypt extends Injectable
{
    private $key = null;

    private $iv = null;

    public function __construct()
    {
        $this->addDependency('config');
    }

    protected function getKey()
    {
        if (empty($this->key)) {
            $this->key = hash('sha256', $this->getInjection('config')->get('cryptKey', ''), true);
        }
        return $this->key;
    }

    protected function getIv()
    {
        if (empty($this->iv)) {
            if (extension_loaded('openssl')) {
                $this->iv = openssl_random_pseudo_bytes(16);
            } else {
                $this->iv = mcrypt_create_iv(16, MCRYPT_RAND);
            }
        }
        return $this->iv;
    }

    public function encrypt($string)
    {
        $iv = $this->getIv();
        if (extension_loaded('openssl')) {
            return base64_encode(openssl_encrypt($string, 'aes-256-cbc', $this->getKey(), OPENSSL_RAW_DATA , $iv) . $iv);
        } else {
            $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
            $pad = $block - (strlen($string) % $block);
            $string .= str_repeat(chr($pad), $pad);
            return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->getKey(), $string, MCRYPT_MODE_CBC, $iv) . $iv);
        }
    }

    public function decrypt($encryptedString)
    {
        $encryptedString = base64_decode($encryptedString);
        $string = substr($encryptedString, 0, strlen($encryptedString) - 16);
        $iv = substr($encryptedString, -16);

        if (extension_loaded('openssl')) {
            return trim(openssl_decrypt($string, 'aes-256-cbc', $this->getKey(), OPENSSL_RAW_DATA, $iv));
        } else {
            $string = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->getKey(), $string, MCRYPT_MODE_CBC, $iv);
            $block = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
            $len = strlen($string);
            $pad = ord($string[$len - 1]);
            return substr($string, 0, strlen($string) - $pad);
        }
    }

    public function generateKey()
    {
        return md5(uniqid());
    }
}


<?php

namespace Espo\Core\Utils;

class Crypt
{
    private $config;

    private $key = null;

    private $cryptKey = null;

    private $iv = null;

    public function __construct($config)
    {
        $this->config = $config;
        $this->cryptKey = $config->get('cryptKey', '');
    }

    protected function getKey()
    {
        if (empty($this->key)) {
            $this->key = hash('sha256', $this->cryptKey, true);
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


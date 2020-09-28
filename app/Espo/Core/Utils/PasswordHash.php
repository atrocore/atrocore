<?php

namespace Espo\Core\Utils;
use Espo\Core\Exceptions\Error;

class PasswordHash
{
    private $config;

    /**
     * Salt format of SHA-512
     *
     * @var string
     */
    private $saltFormat = '$6${0}$';

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Get hash of a pawword
     *
     * @param  string $password
     * @return string
     */
    public function hash($password, $useMd5 = true)
    {
        $salt = $this->getSalt();

        if ($useMd5) {
            $password = md5($password);
        }

        $hash = crypt($password, $salt);
        $hash = str_replace($salt, '', $hash);

        return $hash;
    }

    /**
     * Get a salt from config and normalize it
     *
     * @return string
     */
    protected function getSalt()
    {
        $salt = $this->getConfig()->get('passwordSalt');
        if (!isset($salt)) {
            throw new Error('Option "passwordSalt" does not exist in config.php');
        }

        $salt = $this->normalizeSalt($salt);

        return $salt;
    }

    /**
     * Convert salt in format in accordance to $saltFormat
     *
     * @param  string $salt
     * @return string
     */
    protected function normalizeSalt($salt)
    {
        return str_replace("{0}", $salt, $this->saltFormat);
    }

    /**
     * Generate a new salt
     *
     * @return string
     */
    public function generateSalt()
    {
        return substr(md5(uniqid()), 0, 16);
    }
}
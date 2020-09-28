<?php

namespace Espo\Core\Utils\Authentication;

use \Espo\Core\Utils\Config;
use \Espo\Core\ORM\EntityManager;
use \Espo\Core\Utils\Auth;

abstract class Base
{
    private $config;

    private $entityManager;

    private $auth;

    private $passwordHash;

    public function __construct(Config $config, EntityManager $entityManager, Auth $auth)
    {
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->auth = $auth;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getAuth()
    {
        return $this->auth;
    }

    protected function getPasswordHash()
    {
        if (!isset($this->passwordHash)) {
            $this->passwordHash = new \Espo\Core\Utils\PasswordHash($this->config);
        }

        return $this->passwordHash;
    }
}


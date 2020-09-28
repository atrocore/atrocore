<?php

namespace Espo\Jobs;

use \Espo\Core\Exceptions;

class AuthTokenControl extends \Espo\Core\Jobs\Base
{
    public function run()
    {
        $authTokenLifetime = $this->getConfig()->get('authTokenLifetime');
        $authTokenMaxIdleTime = $this->getConfig()->get('authTokenMaxIdleTime');

        if (!$authTokenLifetime && !$authTokenMaxIdleTime) {
            return;
        }

        $whereClause = array(
            'isActive' => true
        );

        if ($authTokenLifetime) {
            $dt = new \DateTime();
            $dt->modify('-' . $authTokenLifetime . ' hours');
            $authTokenLifetimeThreshold = $dt->format('Y-m-d H:i:s');

            $whereClause['createdAt<'] = $authTokenLifetimeThreshold;
        }

        if ($authTokenMaxIdleTime) {
            $dt = new \DateTime();
            $dt->modify('-' . $authTokenMaxIdleTime . ' hours');
            $authTokenMaxIdleTimeThreshold = $dt->format('Y-m-d H:i:s');

            $whereClause['lastAccess<'] = $authTokenMaxIdleTimeThreshold;
        }

        $tokenList = $this->getEntityManager()->getRepository('AuthToken')->where($whereClause)->limit(0, 500)->find();

        foreach ($tokenList as $token) {
            $token->set('isActive', false);
            $this->getEntityManager()->saveEntity($token);
        }
    }
}


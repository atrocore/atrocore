<?php

namespace Espo\Core\Utils\Authentication;

use \Espo\Core\Exceptions\Error;

class Espo extends Base
{
    /**
     * @param string $username
     * @param string $password
     * @param mixed  $authToken
     * @param mixed  $isPortal
     *
     * @return mixed
     * @throws Error
     */
    public function login($username, $password, \Espo\Entities\AuthToken $authToken = null, $isPortal = null)
    {
        // is system updating ?
        $this->isUpdating($authToken);

        if ($authToken) {
            $hash = $authToken->get('hash');
        } else {
            $hash = $this->getPasswordHash()->hash($password);
        }

        $user = $this->getEntityManager()->getRepository('User')->findOne(array(
            'whereClause' => array(
                'userName' => $username,
                'password' => $hash
            )
        ));

        return $user;
    }

    /**
     * @param mixed $authToken
     *
     * @throws Error
     */
    protected function isUpdating($authToken)
    {
        if (is_null($authToken) && !empty($this->getConfig()->get('isUpdating'))) {
            throw new Error('System is updating now! Please try later.');
        }
    }
}

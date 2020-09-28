<?php

namespace Espo\Core\Utils;

use \Espo\Core\ORM\EntityManager;
use \Espo\Entities\Email;

class EmailFilterManager
{
    private $entityManager;

    private $data = array();

    protected $filtersMatcher = null;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getFiltersMatcher()
    {
        if (!$this->filtersMatcher) {
            $this->filtersMatcher = new \Espo\Core\Mail\FiltersMatcher();
        }
        return $this->filtersMatcher;
    }

    public function getMatchingFilter(Email $email, $userId)
    {
        if (!array_key_exists($userId, $this->data)) {
            $emailFilterList = $this->getEntityManager()->getRepository('EmailFilter')->where(array(
                'parentId' => $userId,
                'parentType' => 'User'
            ))->order('LIST:action:Skip;Move to Folder')->find();
            $this->data[$userId] = $emailFilterList;
        }
        foreach ($this->data[$userId] as $emailFilter) {
            if ($this->getFiltersMatcher()->match($email, $emailFilter)) {
                return $emailFilter;
            }
        }
    }
}



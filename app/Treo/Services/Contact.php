<?php

namespace Treo\Services;

use \Espo\ORM\Entity;

/**
 * Class Contact
 *
 * @package Treo\Services
 */
class Contact extends Person
{
    /**
     * @var array
     */
    protected $readOnlyAttributeList = [
        'inboundEmailId',
        'portalUserId'
    ];
    /**
     * @var array
     */
    protected $exportAllowedAttributeList = [
        'title'
    ];
    /**
     * @var array
     */
    protected $mandatorySelectAttributeList = [
        'accountId',
        'accountName'
    ];

    /**
     * @param Entity $entity
     * @param $data
     */
    protected function afterCreateEntity(Entity $entity, $data)
    {
        if (!empty($data->emailId)) {
            $email = $this->getEntityManager()->getEntity('Email', $data->emailId);
            if ($email && !$email->get('parentId')) {
                if ($this->getConfig()->get('b2cMode')) {
                    $email->set(array(
                        'parentType' => 'Contact',
                        'parentId' => $entity->id
                    ));
                } else {
                    if ($entity->get('accountId')) {
                        $email->set(array(
                            'parentType' => 'Account',
                            'parentId' => $entity->get('accountId')
                        ));
                    }
                }
                $this->getEntityManager()->saveEntity($email);
            }
        }
    }
}

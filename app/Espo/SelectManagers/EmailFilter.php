<?php

namespace Espo\SelectManagers;

class EmailFilter extends \Espo\Core\SelectManagers\Base
{
    protected function boolFilterOnlyMy(&$result)
    {
        $this->accessOnlyOwn($result);
    }

    protected function accessOnlyOwn(&$result)
    {
        $d = array();
        $d[] = array(
            'parentType' => 'User',
            'parentId' => $this->getUser()->id
        );

        $idList = [];
        $emailAccountList = $this->getEntityManager()->getRepository('EmailAccount')->where(array(
            'assignedUserId' => $this->getUser()->id
        ))->find();
        foreach ($emailAccountList as $emailAccount) {
            $idList = $emailAccount->id;
        }

        if (count($idList)) {
            $d = array(
                'OR' => array(
                    $d,
                    array(
                        'parentType' => 'EmailAccount',
                        'parentId' => $idList
                    )
                )

            );
        }
        $result['whereClause'][] = $d;
    }
}


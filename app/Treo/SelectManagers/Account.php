<?php

namespace Treo\SelectManagers;

/**
 * Class Account
 *
 * @package Treo\SelectManagers
 */
class Account extends \Espo\Core\SelectManagers\Base
{
    /**
     * @param $result
     */
    protected function filterPartners(&$result)
    {
        $result['whereClause'][] = array(
            'type' => 'Partner'
        );
    }

    /**
     * @param $result
     */
    protected function filterCustomers(&$result)
    {
        $result['whereClause'][] = array(
            'type' => 'Customer'
        );
    }

    /**
     * @param $result
     */
    protected function filterResellers(&$result)
    {
        $result['whereClause'][] = array(
            'type' => 'Reseller'
        );
    }

    /**
     * @param $result
     *
     * @throws \Exception
     */
    protected function filterRecentlyCreated(&$result)
    {
        $dt = new \DateTime('now');
        $dt->modify('-7 days');

        $result['whereClause'][] = array(
            'createdAt>=' => $dt->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param $result
     */
    protected function accessPortalOnlyAccount(&$result)
    {
        $accountIdList = $this->getUser()->getLinkMultipleIdList('accounts');

        if (count($accountIdList)) {
            $result['whereClause'][] = array(
                'id' => $accountIdList
            );
        } else {
            $result['whereClause'][] = array(
                'id' => null
            );
        }
    }
}

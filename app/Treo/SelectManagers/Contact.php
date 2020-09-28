<?php

namespace Treo\SelectManagers;

/**
 * Class Contact
 *
 * @package Treo\SelectManagers
 */
class Contact extends \Espo\Core\SelectManagers\Base
{
    /**
     * @param $result
     */
    protected function filterPortalUsers(&$result)
    {
        $result['customJoin'] .= " JOIN user AS portalUser ON portalUser.contact_id = contact.id 
        AND portalUser.deleted = 0 ";
    }

    /**
     * @param $result
     */
    protected function filterNotPortalUsers(&$result)
    {
        $result['customJoin'] .= " LEFT JOIN user AS portalUser ON portalUser.contact_id = contact.id 
        AND portalUser.deleted = 0 ";
        $this->addAndWhere(array(
            'portalUser.id' => null
        ), $result);
    }

    /**
     * @param $result
     */
    protected function accessPortalOnlyContact(&$result)
    {
        $d = array();

        $contactId = $this->getUser()->get('contactId');

        if ($contactId) {
            $result['whereClause'][] = array(
                'id' => $contactId
            );
        } else {
            $result['whereClause'][] = array(
                'id' => null
            );
        }
    }

    /**
     * @param $result
     */
    protected function filterAccountActive(&$result)
    {
        if (!array_key_exists('additionalColumnsConditions', $result)) {
            $result['additionalColumnsConditions'] = array();
        }
        $result['additionalColumnsConditions']['isInactive'] = false;
    }
}

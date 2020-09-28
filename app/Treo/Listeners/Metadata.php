<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class Metadata
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Metadata extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        // add owner
        $data = $this->addOwner($data);

        // add onlyActive bool filter
        $data = $this->addOnlyActiveFilter($data);

        // set data
        $event->setArgument('data', $data);
    }

    /**
     * Add owner, assigned user, team if it needs
     *
     * @param array $data
     *
     * @return array
     */
    protected function addOwner(array $data): array
    {
        foreach ($data['scopes'] as $scope => $row) {
            // for owner user
            if (!empty($row['hasOwner'])) {
                if (empty($data['entityDefs'][$scope]['fields']['ownerUser'])) {
                    $data['entityDefs'][$scope]['fields']['ownerUser'] = [
                        "type"     => "link",
                        "required" => true,
                        "view"     => "views/fields/owner-user"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['ownerUser'])) {
                    $data['entityDefs'][$scope]['links']['ownerUser'] = [
                        "type"   => "belongsTo",
                        "entity" => "User"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['indexes']['ownerUser'])) {
                    $data['entityDefs'][$scope]['indexes']['ownerUser'] = [
                        "columns" => [
                            "ownerUserId",
                            "deleted"
                        ]
                    ];
                }
            }

            // for assigned user
            if (!empty($row['hasAssignedUser'])) {
                if (empty($data['entityDefs'][$scope]['fields']['assignedUser'])) {
                    $data['entityDefs'][$scope]['fields']['assignedUser'] = [
                        "type"     => "link",
                        "required" => true,
                        "view"     => "views/fields/owner-user"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['assignedUser'])) {
                    $data['entityDefs'][$scope]['links']['assignedUser'] = [
                        "type"   => "belongsTo",
                        "entity" => "User"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['indexes']['assignedUser'])) {
                    $data['entityDefs'][$scope]['indexes']['assignedUser'] = [
                        "columns" => [
                            "assignedUserId",
                            "deleted"
                        ]
                    ];
                }
            }

            // for teams
            if (!empty($row['hasTeam'])) {
                if (empty($data['entityDefs'][$scope]['fields']['teams'])) {
                    $data['entityDefs'][$scope]['fields']['teams'] = [
                        "type" => "linkMultiple",
                        "view" => "views/fields/teams"
                    ];
                }
                if (empty($data['entityDefs'][$scope]['links']['teams'])) {
                    $data['entityDefs'][$scope]['links']['teams'] = [
                        "type"                        => "hasMany",
                        "entity"                      => "Team",
                        "relationName"                => "EntityTeam",
                        "layoutRelationshipsDisabled" => true
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Remove field from index
     *
     * @param array  $indexes
     * @param string $fieldName
     *
     * @return array
     */
    protected function removeFieldFromIndex(array $indexes, string $fieldName): array
    {
        foreach ($indexes as $indexName => $fields) {
            // search field in index
            $key = array_search($fieldName, $fields['columns']);
            // remove field if exists
            if ($key !== false) {
                unset($indexes[$indexName]['columns'][$key]);
            }
        }

        return $indexes;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function addOnlyActiveFilter(array $data): array
    {
        foreach ($data['entityDefs'] as $entity => $row) {
            if (isset($row['fields']['isActive']['type']) && $row['fields']['isActive']['type'] == 'bool') {
                // push
                $data['clientDefs'][$entity]['boolFilterList'][] = 'onlyActive';
            }
        }

        return $data;
    }
}

<?php

namespace Espo\Core\Utils\Database\Orm\Relations;

class BelongsTo extends Base
{
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();

        $foreignEntityName = $this->getForeignEntityName();
        $foreignLinkName = $this->getForeignLinkName();

        $index = true;
        if (!empty($linkParams['noIndex'])) {
            $index = false;
        }

        $noForeignName = false;
        if (!empty($linkParams['noForeignName'])) {
            $noForeignName = true;
        } else {
            if (!empty($linkParams['foreignName'])) {
                $foreign = $linkParams['foreignName'];
            } else {
                $foreign = $this->getForeignField('name', $foreignEntityName);
            }
        }

        if (!empty($linkParams['noJoin'])) {
            $fieldNameDefs = array(
                'type' => 'varchar',
                'notStorable' => true,
                'relation' => $linkName,
                'foreign' => $this->getForeignField('name', $foreignEntityName),
            );
        } else {
            $fieldNameDefs = array(
                'type' => 'foreign',
                'relation' => $linkName,
                'foreign' => $foreign,
                'notStorable' => false
            );
        }

        $data = array (
            $entityName => array (
                'fields' => array(
                    $linkName.'Id' => array(
                        'type' => 'foreignId',
                        'index' => $index
                    )
                ),
                'relations' => array(
                    $linkName => array(
                        'type' => 'belongsTo',
                        'entity' => $foreignEntityName,
                        'key' => $linkName.'Id',
                        'foreignKey' => 'id',
                        'foreign' => $foreignLinkName
                    )
                )
            )
        );

        if (!$noForeignName) {
            $data[$entityName]['fields'][$linkName.'Name'] = $fieldNameDefs;
        }

        return $data;
    }

}
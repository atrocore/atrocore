<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class Schema
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class Schema extends AbstractListener
{
    /**
     * Prepare entityDefs before rebuild action
     *
     * @param Event $event
     */
    public function beforeRebuild(Event $event)
    {
        // prepare LONGTEXT default
        $this->prepareLongTextDefault($event);
    }

    /**
     * Prepare comment default value
     *
     * @param array $data
     *
     * @return array
     */
    protected function prepareCommentDefaultValue(array $data): array
    {
        foreach ($data as $key => $query) {
            if (preg_match("/COMMENT 'default={(.*)}'/s", $query)) {
                $data[$key] = str_replace("\n", "{break}", $query);
            }
        }

        return $data;
    }

    /**
     * Prepare LONGTEXT default value
     *
     * @param Event $event
     */
    protected function prepareLongTextDefault(Event $event)
    {
        $queries = $this->prepareCommentDefaultValue($event->getArgument('queries'));

        foreach ($queries as $key => $query) {
            // prepare fields
            $fields = [];
            while (preg_match_all(
                "/^.* (.*) (MEDIUMTEXT|LONGTEXT) DEFAULT NULL COMMENT 'default={(.*)}'/",
                $query,
                $matches
            )) {
                // prepare data
                $field = $matches[1][0];
                $type = $matches[2][0];
                $value = $matches[3][0];

                // push
                $fields[$field] = $value;

                // remove from query
                $query =
                    str_replace("{$field} {$type} DEFAULT NULL COMMENT 'default={{$value}}'", "", $query);
            }

            // prepare table name
            if (!empty($fields) && preg_match_all("/^ALTER TABLE `(.*)` .*$/", $query, $matches)) {
                $tableName = explode("`", $matches[1][0])[0];
            }

            if (!empty($tableName) && !empty($fields)) {
                foreach ($fields as $field => $value) {
                    $queries[$key]
                        .= ";UPDATE {$tableName} SET {$field}='{$this->parseDefaultValue($value)}'
                         WHERE {$field} IS NULL";
                }
            }
        }
        $event->setArgument('queries', $queries);
    }

    /**
     * Parse default value
     *
     * @param string $value
     *
     * @return string
     */
    protected function parseDefaultValue(string $value): string
    {
        if (!empty($value) && preg_match("/({break})+/", $value)) {
            $value = str_replace("{break}", "\n", $value);
        }

        return $value;
    }
}

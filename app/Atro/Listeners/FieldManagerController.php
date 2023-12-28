<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;

/**
 * Class FieldManagerController
 */
class FieldManagerController extends AbstractListener
{
    public function beforePostActionCreate(Event $event): void
    {
        $data = $event->getArgument('data');
        $params = $event->getArgument('params');

        // is default value valid ?
        if (property_exists($event->getArgument('data'), 'default')) {
            $this->isDefaultValueValid($data->type, $event->getArgument('data')->default);
        }

        if (property_exists($data, 'unique') && !empty($data->unique)) {
            $this->isUniqueFieldWithoutDuplicates($params['scope'], $data->name);
        }

        if (property_exists($data, 'pattern') && !empty($data->pattern)) {
            $pattern = $data->pattern;
            if (!preg_match("/^\/(.*)\/$/", $pattern)) {
                throw new BadRequest($this->getLanguage()->translate('regexNotValid', 'exceptions', 'FieldManager'));
            }

            $field = Util::toUnderScore($data->name);

            if (!$this->getMetadata()->get(['entityDefs', $params['scope'], 'fields', $field])) {
                return;
            }

            $table = Util::toUnderScore($params['scope']);

            $fields = [$field];
            if (!in_array($data->type, ['enum', 'multiEnum']) && $this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $languageField = $field . '_' . strtolower($locale);
                    if ($this->getMetadata()->get(['entityDefs', $params['scope'], 'fields', Util::toCamelCase($languageField)])) {
                        $fields[] = $languageField;
                    }
                }
            }

            $connection = $this->getEntityManager()->getConnection();

            $wheres = [];
            foreach ($fields as $v) {
                $wheres[] = "{$connection->quoteIdentifier($v)} IS NOT NULL AND {$connection->quoteIdentifier($v)} != ''";
            }

            $sth = $this->getEntityManager()->getPDO()->prepare("SELECT * FROM {$connection->quoteIdentifier($table)} WHERE deleted=:deleted AND (" . implode(' OR ', $wheres) . ")");
            $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
            $sth->execute();
            $records = $sth->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($records as $valueData) {
                foreach ($fields as $v) {
                    if (!empty($valueData[$v]) && !preg_match($pattern, $valueData[$v])) {
                        throw new BadRequest($this->getLanguage()->translate('someFieldDontMathToPattern', 'exceptions', 'FieldManager'));
                    }
                }
            }
        }
    }

    /**
     * @param Event $event
     */
    public function beforePatchActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforePutActionUpdate(Event $event)
    {
        $this->beforePostActionCreate($event);
    }

    /**
     * @param Event $event
     */
    public function beforeDeleteActionDelete(Event $event)
    {
        // delete columns from DB
        $this->deleteColumns($event->getArgument('params')['scope'], $event->getArgument('params')['name']);
    }

    /**
     * Delete column(s) from DB
     *
     * @param string $scope
     * @param string $field
     */
    protected function deleteColumns(string $scope, string $field): void
    {
        // get field metadata
        $fields = $this
            ->getContainer()
            ->get('metadata')
            ->getFieldList($scope, $field);

        if (!empty($fields)) {
            // prepare table name
            $table = Util::toUnderScore($scope);

            foreach ($fields as $name => $row) {
                // prepare column
                $column = Util::toUnderScore($name);
                switch ($row['type']) {
                    case 'file':
                        $column .= '_id';
                        break;
                    case 'image':
                        $column .= '_id';
                        break;
                    case 'asset':
                        $column .= '_id';
                        break;
                }

                try {
                    // execute SQL
                    $sth = $this
                        ->getEntityManager()
                        ->getPDO()
                        ->prepare("ALTER TABLE {$table} DROP COLUMN {$column};");
                    $sth->execute();
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Is default value valid
     *
     * @param string $type
     * @param mixed  $default
     *
     * @return bool
     * @throws BadRequest
     */
    protected function isDefaultValueValid(string $type, $default): bool
    {
        // prepare types
        $types = ['text', 'textMultiLang', 'wysiwyg', 'wysiwygMultiLang'];

        if (in_array($type, $types) && is_string($default) && strpos($default, "'") !== false) {
            // prepare message
            $message = $this
                ->getLanguage()
                ->translate('defaultValidationFailed', 'messages', 'FieldManager');

            throw new BadRequest($message);
        }

        return true;
    }

    /**
     * @param string $scope
     * @param string $field
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function isUniqueFieldWithoutDuplicates(string $scope, string $field): void
    {
        $defs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field], []);

        if (isset($defs['type'])) {
            $table = Util::toUnderScore($scope);
            $field = Util::toUnderScore($field);

            switch ($defs['type']) {
                case 'asset':
                    $this->removeDeletedDuplicate($table, [$field . '_id']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.{$field}_id IS NOT NULL AND deleted = :deleted GROUP BY $table.{$field}_id HAVING COUNT($table.{$field}_id) > 1";
                    $result = $this->fetch($sql, [':deleted' => false]);
                    break;
                case 'unit':
                    $this->removeDeletedDuplicate($table, [$field, $field . '_unit']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.$field IS NOT NULL AND {$field}_unit IS NOT NULL AND deleted = :deleted GROUP BY $table.$field, {$field}_unit HAVING COUNT($table.$field) > 1 AND COUNT({$field}_unit) > 1";
                    $result = $this->fetch($sql, [':deleted' => false]);
                    break;
                case 'rangeInt':
                case 'rangeFloat':
                    $this->removeDeletedDuplicate($table, [$field . '_from', $field . '_to']);

                    $sql = "SELECT COUNT(*) FROM $table WHERE {$field}_from IS NOT NULL AND {$field}_to IS NOT NULL AND deleted=:deleted GROUP BY {$field}_from, {$field}_to HAVING COUNT({$field}_from) > 1 AND COUNT({$field}_to) > 1";
                    $result = $this->fetch($sql, [':deleted' => false]);
                    break;
                default:
                    $this->removeDeletedDuplicate($table, [$field]);

                    $sql = "SELECT COUNT(*) FROM $table WHERE $table.$field IS NOT NULL AND deleted = :deleted GROUP BY $table.$field HAVING COUNT($table.$field) > 1;";
                    $result = $this->fetch($sql, [':deleted' => false]);

                    if (!$result && !empty($defs['isMultilang']) && $this->getConfig()->get('isMultilangActive', false)) {
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                            $locale = strtolower($locale);

                            $this->removeDeletedDuplicate($table, [$field . '_' . $locale]);

                            $sql = "SELECT COUNT(*) FROM $table WHERE $table.{$field}_$locale IS NOT NULL AND deleted = :deleted GROUP BY $table.{$field}_$locale HAVING COUNT($table.{$field}_$locale) > 1;";
                            $result = $result || $this->fetch($sql, [':deleted' => false]);
                        }
                    }
            }

            if (!empty($result)) {
                $message = $this
                    ->getLanguage()
                    ->translate('someFieldNotUnique', 'exceptions', 'FieldManager');

                throw new BadRequest($message);
            }
        }
    }

    /**
     * @param string $table
     * @param array $fields
     */
    protected function removeDeletedDuplicate(string $table, array $fields): void
    {
        $connection = $this->getEntityManager()->getConnection();

        $sql = "SELECT DISTINCT first.id AS id FROM {$connection->quoteIdentifier($table)} as first, {$connection->quoteIdentifier($table)} as second WHERE first.id <> second.id AND first.deleted = :deleted";
        foreach ($fields as $field) {
            $sql .= " AND first.$field = second.$field";
        }

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->bindValue(':deleted', false, \PDO::PARAM_BOOL);
        $sth->execute();

        $notUniqueDeletedIds = $sth->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_COLUMN);

        if (!empty($notUniqueDeletedIds)) {
            $connection->createQueryBuilder()
                ->delete($connection->quoteIdentifier($table))
                ->where('id IN (:ids)')
                ->setParameter('ids', $notUniqueDeletedIds, Mapper::getParameterType($notUniqueDeletedIds))
                ->executeQuery();
        }
    }

    /**
     * @param string $sql
     * @param array $params
     *
     * @return mixed
     */
    protected function fetch(string $sql, array $params = [])
    {
        $pdo = $this
            ->getContainer()
            ->get('pdo');
        $sth = $pdo->prepare($sql);
        foreach ($params as $name => $value) {
            if (is_bool($value)) {
                $sth->bindValue($name, $value, \PDO::PARAM_BOOL);
            } else {
                $sth->bindValue($name, $value);
            }
        }

        $sth->execute();

        return $sth->fetch();
    }
}

<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
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

            $sth = $this->getEntityManager()->getPDO()->prepare(
                "SELECT * FROM {$connection->quoteIdentifier($table)} WHERE deleted=:deleted AND (" . implode(' OR ', $wheres) . ")"
            );
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

    protected function isUniqueFieldWithoutDuplicates(string $scope, string $field): void
    {
        $defs = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field], []);

        if (isset($defs['type'])) {
            $table = Util::toUnderScore($scope);
            $column = Util::toUnderScore($field);

            /** @var Connection $conn */
            $conn = $this->getContainer()->get('connection');

            $res = $conn->createQueryBuilder()
                ->select("t3.$column, COUNT(*)")
                ->from($conn->quoteIdentifier($table), 't3')
                ->where("t3.$column IS NOT NULL")
                ->andWhere('t3.deleted = :false')
                ->groupBy("t3.$column")
                ->having("COUNT(t3.$column) > 1")
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            if (empty($res) && !empty($defs['isMultilang']) && $this->getConfig()->get('isMultilangActive', false)) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $locale = strtolower($locale);
                    $res = $conn->createQueryBuilder()
                        ->select("t3.$column, COUNT(*)")
                        ->from($conn->quoteIdentifier($table), 't3')
                        ->where("t3.{$column}_{$locale} IS NOT NULL")
                        ->andWhere('t3.deleted = :false')
                        ->groupBy("t3.{$column}_{$locale}")
                        ->having("COUNT(t3.{$column}_{$locale}) > 1")
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->fetchAllAssociative();

                    if (!empty($res)) {
                        break;
                    }
                }
            }

            if (!empty($res)) {
                $message = $this->getLanguage()->translate('someFieldNotUnique', 'exceptions', 'FieldManager');
                if ($this->getConfig()->get('hasQueryBuilderFilter')) {
                    $rules = [];
                    foreach ($res as $item) {
                        $rules[] = ['id' => $field, 'operator' => 'equal', 'value' => $item[$column]];
                    }
                    $where = ['condition' => 'OR', 'rules' => $rules];
                    $url = $this->getConfig()->get('siteUrl') . '/?where=' . htmlspecialchars(json_encode($where), ENT_QUOTES, 'UTF-8') . '#' . $scope;
                    $message .= ' <a href="' . $url . '" target="_blank">' . $this->getLanguage()->translate('See more') . '</a>.';
                }
                throw new BadRequest($message);
            }
        }
    }
}

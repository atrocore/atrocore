<?php

namespace Treo\Services;

use Espo\Entities\User;
use Espo\Core\Utils\Config;
use Espo\Orm\EntityManager;
use Treo\Core\EventManager\Event;
use Treo\Core\Interfaces\ServiceInterface;

/**
 * AbstractService class
 *
 * @author r.ratsun@zinitsolutions.com
 */
abstract class AbstractService implements ServiceInterface
{
    use \Treo\Traits\ContainerTrait;

    /**
     * Reload dependency
     *
     * @param string $name
     */
    protected function reloadDependency($name)
    {
        $this->getContainer()->reload($name);
    }

    /**
     * Rebuild
     */
    protected function rebuild(): void
    {
        $this->reloadDependency('entityManager');
        $this->getContainer()->get('dataManager')->rebuild();
    }

    /**
     * Get EntityManager
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * Get Config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get User
     *
     * @return User
     */
    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    /**
     * Dispatch an event
     *
     * @param string $target
     * @param string $action
     * @param Event  $event
     *
     * @return array
     */
    protected function dispatch(string $target, string $action, Event $event)
    {
        $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch($target, $action, $event);
    }

    /**
     * Translate
     *
     * @param string     $label
     * @param string     $category
     * @param string     $scope
     * @param array|null $requiredOptions
     *
     * @return string
     */
    protected function translate(string $label, string $category = 'labels', string $scope = 'Global', array $requiredOptions = null): string
    {
        return $this
            ->getContainer()
            ->get('language')
            ->translate($label, $category, $scope, $requiredOptions);
    }

    /**
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translateException(string $label, string $scope = 'Global'): string
    {
        return $this->getContainer()->get('language')->translateException($label, $scope);
    }

    /**
     * Execute SQL query
     *
     * @param string $sql
     *
     * @return mixed
     */
    protected function executeSqlQuery(string $sql)
    {
        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        return $sth;
    }

    /**
     * Execute SQL SELECT query
     *
     * @param string $sql
     *
     * @return array
     */
    protected function executeSqlSelectQuery(string $sql): array
    {
        $data = $this->executeSqlQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return (empty($data)) ? [] : $data;
    }

    /**
     * @param      $filename
     * @param      $data
     * @param int  $flags
     * @param null $context
     *
     * @return bool|int
     */
    protected function filePutContants($filename, $data, $flags = 0, $context = null)
    {
        return file_put_contents($filename, $data, $flags, $context);
    }
}

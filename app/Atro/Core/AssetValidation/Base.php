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

namespace Atro\Core\AssetValidation;

use Espo\Core\Utils\Util;
use Espo\Core\Container;
use Espo\Core\ORM\EntityManager;

abstract class Base
{
    /**
     * @var
     */
    protected $params;
    /**
     * @var Container
     */
    protected $container;
    /**
     * @var
     */
    protected $attachment;

    /**
     * Base constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param $attachment
     *
     * @return $this
     */
    public function setAttachment($attachment)
    {
        $this->attachment = $attachment;

        return $this;
    }

    /**
     * @param $params
     *
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @return bool
     */
    abstract public function validate(): bool;

    /**
     * @return mixed
     */
    abstract public function onValidateFail();

    /**
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected function getRepository(string $name)
    {
        return $this->getEntityManager()->getRepository($name);
    }

    /**
     * @return mixed
     */
    protected function getUser()
    {
        return $this->container->get('user');
    }

    /**
     * @param string $label
     * @param string $category
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $label, string $category, string $scope): string
    {
        return $this->container->get("language")->translate($label, $category, $scope);
    }

    /**
     * @param string $label
     * @param string $category
     * @param string $scope
     *
     * @return string
     */
    protected function exception(string $label): string
    {
        return $this->translate($label, 'exceptions', 'Global');
    }

    /**
     * @return string
     */
    protected function getFilePath(): string
    {
        $path = $this->getEntityManager()->getRepository('Attachment')->getFilePath($this->attachment);

        if (!file_exists($path)) {
            $path = '/tmp/' . Util::generateId() . $this->attachment->get('name');
            if (!file_exists($path)) {
                file_put_contents($path, $this->attachment->get('contents'));
            }
        }

        return $path;
    }
}
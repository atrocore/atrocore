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

namespace Atro\Core\FileValidation;

use Atro\Entities\File;
use Atro\Core\Container;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\Core\ORM\EntityManager;

abstract class Base
{
    /**
     * @var mixed
     */
    protected $params;

    protected Container $container;
    protected File $attachment;

    public function __construct(Container $container, File $file, $params)
    {
        $this->container = $container;
        $this->attachment = $file;
        $this->params = $params;
    }

    abstract public function validate(): bool;

    abstract public function onValidateFail();

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getRepository(string $name)
    {
        return $this->getEntityManager()->getRepository($name);
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function translate(string $label, string $category, string $scope): string
    {
        return $this->getLanguage()->translate($label, $category, $scope);
    }

    protected function getLanguage(): Language
    {
        return $this->container->get("language");
    }

    protected function exception(string $label): string
    {
        return $this->translate($label, 'exceptions', 'Global');
    }

    protected function getFilePath(): string
    {
        return $this->attachment->getFilePath();
    }
}
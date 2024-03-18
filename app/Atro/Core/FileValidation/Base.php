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
use Espo\Core\ORM\EntityManager;
use Espo\Entities\User;
use Espo\ORM\Entity;

abstract class Base
{
    protected Container $container;
    protected File $file;
    protected Entity $validationRule;

    public function __construct(Container $container, File $file, Entity $validationRule)
    {
        $this->container = $container;
        $this->file = $file;
        $this->validationRule = $validationRule;
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

    protected function getUser(): User
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
}
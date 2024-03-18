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

namespace Atro\Core;

use Atro\Entities\File as FileEntity;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityManager;

class FileValidator
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function validateFile(FileEntity $entity, bool $error): bool
    {
        $fileType = $this->getEntityManager()->getRepository('FileType')->get($entity->get('typeId'));

        foreach ($fileType->get('validationRules') as $rule) {
            if (empty($rule->get('isActive'))) {
                continue;
            }

            $type = Util::toCamelCase(strtolower(str_replace(' ', '_', $rule->get('type'))));
            $className = "\\Atro\\Core\\FileValidation\\Items\\" . ucfirst($type);

            $validator = new $className($this->container, $rule);
            if (!$validator->validate($entity)) {
                if ($error) {
                    $validator->onValidateFail();
                }
                return false;
            }
        }

        return true;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}
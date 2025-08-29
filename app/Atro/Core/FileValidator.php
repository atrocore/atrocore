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
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class FileValidator
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function validateFile(Entity $fileType, FileEntity $entity, bool $error = false): bool
    {
        $validators = [];

        if (empty($fileType->get('extensions'))) {
            $validators['Extension'] = ['extensions' => $fileType->get('extensions')];
        }
        if (!empty($fileType->get('mimeType'))) {
            $validators['MimeType'] = ['mimeTypes' => $fileType->get('mimeTypes')];
        }
        if (!empty($fileType->get('minSize')) || !empty($fileType->get('maxSize'))) {
            $validators['Size'] = [
                'minSize' => $fileType->get('minSize'),
                'maxSize' => $fileType->get('maxSize')
            ];
        }

        $fileNameParts = explode('.', $entity->get("name"));
        $fileExt = strtolower(array_pop($fileNameParts));

        if (in_array($fileExt, $this->getEntityManager()->getMetadata()->get('app.file.image.extensions', []))) {
            if (!empty($fileType->get('minWidth')) || !empty($fileType->get('minHeight'))) {
                $validators['Scale'] = [
                    'minWidth'  => $fileType->get('minWidth'),
                    'minHeight' => $fileType->get('minHeight')
                ];
            }
            if (!empty($fileType->get('aspectRatio'))) {
                $validators['Ratio'] = ['ratio' => $fileType->get('aspectRatio')];
            }
        }


        foreach ($validators as $type => $params) {
            $className = "\\Atro\\Core\\FileValidation\\Items\\$type";

            $validator = new $className($this->container, $params);
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
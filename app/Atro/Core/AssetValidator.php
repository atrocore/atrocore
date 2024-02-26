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

namespace Atro\Core;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Injectable;
use Espo\ORM\Entity;

class AssetValidator extends Injectable
{
    public function __construct()
    {
        $this->addDependency('configManager');
        $this->addDependency('validator');
        $this->addDependency('entityManager');
        $this->addDependency('language');
    }

    public function validateViaType(string $type, Entity $attachment): void
    {
        if (empty($type)) {
            return;
        }
        $config = $this->getInjection("configManager")->getByType([ConfigManager::getType($type)]);
        if (!empty($config['validations'])) {
            foreach ($config['validations'] as $type => $value) {
                $this->getInjection('validator')->validate($type, clone $attachment, ($value['private'] ?? $value));
            }
        }
    }

    public function validateViaTypes(array $types, Entity $attachment): void
    {
        foreach ($types as $type) {
            $this->validateViaType((string)$type, $attachment);
        }
    }

    public function validate(Entity $asset): void
    {
        $attachment = $this->getInjection('entityManager')->getEntity('Attachment', $asset->get('fileId'));
        if (empty($attachment)) {
            throw new BadRequest($this->getInjection('language')->translate('noAttachmentExist', 'exceptions', 'Asset'));
        }

        $this->validateViaTypes($asset->get('type'), $attachment);
    }
}

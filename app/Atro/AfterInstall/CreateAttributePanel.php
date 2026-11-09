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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Espo\ORM\EntityManager;

/**
 * Creates the default attribute panel of the products.
 */
class CreateAttributePanel implements AfterInstallInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public static function getSortOrder(): int
    {
        return 80;
    }

    public function run(): void
    {
        $panel = $this->entityManager->getRepository('AttributePanel')->get();
        $panel->set([
            'id'        => 'attributeValues',
            'code'      => 'attributeValues',
            'name'      => 'Attributes',
            'sortOrder' => 0,
            'entityId'  => 'Product',
            'default'   => true,
        ]);

        $this->entityManager->saveEntity($panel);
    }
}

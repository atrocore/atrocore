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

namespace Atro\Core\Routing;

/**
 * Declares which entity template types this PSR-15 handler applies to.
 *
 * Use on handler classes alongside #[Route] to mark applicability:
 *   #[EntityType(types: ['Base', 'Hierarchy', 'Archive', 'Relation', 'ReferenceData'])]
 *
 * Handlers without this attribute are not considered entity-type handlers
 * and are routed by their #[Route] path directly (e.g. DashletHandler).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EntityType
{
    public function __construct(
        public readonly array $types,
        public readonly array $excludeEntities = [],
        public readonly array $requires = [],
        public readonly array $requiresAbsent = [],
    ) {
    }
}

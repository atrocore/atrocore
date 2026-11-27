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

#[\Attribute(\Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public readonly string       $path,
        public readonly string|array $methods,
        public readonly string       $summary,
        public readonly string       $description,
        public readonly string       $tag,
        public readonly string       $operationId   = '',
        public readonly bool         $auth          = true,
        public readonly array        $parameters    = [],
        public readonly array        $requestBody   = [],
        public readonly array        $responses     = [],
        public readonly array        $entities      = [],
        public readonly bool         $hidden             = false,
        public readonly bool         $skipActionHistory  = false,
        public readonly ?array       $rateLimit          = null,
        public readonly bool         $deprecated         = false,
    ) {
    }

    /**
     * Returns the operationId for the entity: `{entityName}` and `{entityNamePlural}` of the mask of
     * an EntityTypeHandler are replaced with the entity name and its plural.
     */
    public function getOperationId(string $entityName = ''): string
    {
        if ($entityName === '') {
            return $this->operationId;
        }

        return str_replace(
            ['{entityNamePlural}', '{entityName}'],
            [self::pluralize($entityName), $entityName],
            $this->operationId
        );
    }

    /**
     * English plural of an entity name for a function name, e.g. Address → Addresses, Category → Categories.
     * A name ending with `s` (except `ss` and `us`) or with `Data` is taken as plural already, e.g. GetMsEmails.
     */
    private static function pluralize(string $entityName): string
    {
        if (preg_match('/[^aeiou]y$/', $entityName)) {
            return substr($entityName, 0, -1) . 'ies';
        }

        if (preg_match('/(ss|us|x|z|ch|sh)$/', $entityName)) {
            return $entityName . 'es';
        }

        if (str_ends_with($entityName, 's') || str_ends_with($entityName, 'Data')) {
            return $entityName;
        }

        return $entityName . 's';
    }
}

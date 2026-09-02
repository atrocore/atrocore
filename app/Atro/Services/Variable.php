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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Espo\Core\Services\Base;

/**
 * User-defined variables, stored apart from the configuration.
 *
 * They live in their own file precisely because users put secrets in them: kept
 * in the config they would share a namespace with the system parameters, and
 * every mechanism that exposes the config would risk exposing them too.
 */
class Variable extends Base
{
    public const FILE_PATH = 'data/variables.json';

    /**
     * All variables as 'key' => value. Available to Twig and other server-side
     * script contexts - never to the frontend.
     */
    public static function loadAll(): array
    {
        if (!file_exists(self::FILE_PATH)) {
            return [];
        }

        $data = @json_decode(file_get_contents(self::FILE_PATH), true);

        return is_array($data) ? $data : [];
    }

    public static function defineType($value): string
    {
        $type = 'text';
        if (is_int($value) || is_float($value)) {
            $type = 'float';
        } elseif (is_bool($value)) {
            $type = 'bool';
        } elseif (is_array($value)) {
            $type = 'array';
        }

        return $type;
    }

    public function findEntities(array $params): array
    {
        $variables = [];
        foreach (self::loadAll() as $key => $value) {
            $variables[] = $this->prepareRow($key, $value) + [
                    "_meta" => [
                        "permissions" => [
                            "edit"   => true,
                            "delete" => true
                        ]
                    ]
                ];
        }

        return [
            'total' => count($variables),
            'list'  => $variables
        ];
    }

    public function createEntity(\stdClass $attachment): array
    {
        $key = $attachment->key;

        if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $key)) {
            throw new BadRequest($this->getInjection('language')->translate('variableKeyInvalid', 'exceptions', 'Settings'));
        }

        $variables = self::loadAll();

        if (array_key_exists($key, $variables)) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('variableKeyIsExist', 'exceptions', 'Settings'), $key));
        }

        $value = $attachment->value ?? '';

        if (empty($value)) {
            $value = match ($attachment->type ?? 'text') {
                'bool'  => false,
                'float' => 0,
                'array' => [],
                default => '',
            };
        }

        $variables[$key] = $value;
        $this->saveAll($variables);

        return $this->readEntity($key);
    }

    public function updateEntity(string $id, \stdClass $data): array
    {
        $variables = self::loadAll();

        if (!array_key_exists($id, $variables)) {
            throw new NotFound();
        }

        if (property_exists($data, 'value')) {
            $variables[$id] = $data->value;
            $this->saveAll($variables);
        }

        return $this->readEntity($id);
    }

    public function readEntity(string $id): array
    {
        $variables = self::loadAll();

        if (!array_key_exists($id, $variables)) {
            throw new NotFound();
        }

        return $this->prepareRow($id, $variables[$id]);
    }

    public function deleteEntity(string $id): bool
    {
        $variables = self::loadAll();

        if (!array_key_exists($id, $variables)) {
            throw new NotFound();
        }

        unset($variables[$id]);
        $this->saveAll($variables);

        return true;
    }

    private function prepareRow(string $key, $value): array
    {
        return [
            "id"    => $key,
            "key"   => $key,
            "type"  => self::defineType($value),
            "value" => $value
        ];
    }

    private function saveAll(array $variables): void
    {
        $content = json_encode($variables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $tmp = self::FILE_PATH . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, $content) === false || !rename($tmp, self::FILE_PATH)) {
            @unlink($tmp);
            throw new BadRequest('Cannot save variables.');
        }
    }
}

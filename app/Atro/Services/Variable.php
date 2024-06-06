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

class Variable extends Base
{
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
        foreach ($this->getConfig()->get('variables', []) as $key) {
            $value = $this->getConfig()->get($key, '');
            $variables[] = [
                "id"    => $key,
                "key"   => $key,
                "type"  => self::defineType($value),
                "value" => $value
            ];
        }

        return [
            'total' => count($variables),
            'list'  => $variables
        ];
    }

    public function createEntity(\stdClass $attachment): array
    {
        $variables = $this->getConfig()->get('variables', []);

        $key = $attachment->key;

        // validate key
        if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $key)) {
            throw new BadRequest($this->getInjection('language')->translate('variableKeyInvalid', 'exceptions', 'Settings'));
        }
        if ($key === 'variables' || $this->getConfig()->has($key)) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('variableKeyIsExist', 'exceptions', 'Settings'), $key));
        }

        $variables[] = $key;

        $type = $attachment->type ?? 'text';
        $value = $attachment->value ?? '';

        if (empty($value)) {
            switch ($type) {
                case 'bool':
                    $value = false;
                    break;
                case 'float':
                    $value = 0;
                    break;
                case 'array':
                    $value = [];
                    break;
            }
        }

        $this->getConfig()->set('variables', $variables);
        $this->getConfig()->set($key, $value);
        $this->getConfig()->save();

        return $this->readEntity($key);
    }

    public function updateEntity(string $id, \stdClass $data): array
    {
        $variables = $this->getConfig()->get('variables', []);
        if (!in_array($id, $variables)) {
            throw new NotFound();
        }

        if (property_exists($data, 'value')) {
            $this->getConfig()->set($id, $data->value);
            $this->getConfig()->save();
        }

        return $this->readEntity($id);
    }

    public function readEntity(string $id): array
    {
        $variables = $this->getConfig()->get('variables', []);
        if (!in_array($id, $variables)) {
            throw new NotFound();
        }

        $value = $this->getConfig()->get($id, '');

        return [
            "id"    => $id,
            "key"   => $id,
            "type"  => self::defineType($value),
            "value" => $value
        ];
    }

    public function deleteEntity(string $id): bool
    {
        $variables = $this->getConfig()->get('variables', []);
        if (!in_array($id, $variables)) {
            throw new NotFound();
        }

        $newVariables = [];
        foreach ($variables as $key) {
            if ($key !== $id) {
                $newVariables[] = $key;
            }
        }

        $this->getConfig()->set('variables', $newVariables);
        $this->getConfig()->remove($id);
        $this->getConfig()->save();

        return true;
    }
}

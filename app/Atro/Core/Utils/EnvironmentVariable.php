<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Utils;

final readonly class EnvironmentVariable
{
    public function __construct(public string $variable, public string $type = 'string')
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $variable)
            || !in_array($type, ['string', 'json'], true)) {
            throw new \InvalidArgumentException('Invalid environment variable binding.');
        }
    }

    public function resolve(): mixed
    {
        $value = getenv($this->variable);
        if ($value === false || $value === '') {
            throw new \RuntimeException(sprintf("Required environment variable '%s' is missing or empty.", $this->variable));
        }
        if ($this->type === 'string') {
            return $value;
        }
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Do not chain an exception that might expose secret input.
            throw new \RuntimeException(sprintf("Environment variable '%s' must contain valid JSON.", $this->variable));
        }
    }
}
